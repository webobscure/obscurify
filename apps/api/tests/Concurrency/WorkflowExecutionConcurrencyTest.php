<?php

use App\Domain\Automation\Application\CreateWorkflow;
use App\Domain\Automation\Application\DispatchWorkflowTriggersForEvent;
use App\Domain\Automation\Application\PublishWorkflow;
use App\Domain\Automation\Enums\WorkflowExecutionStatus;
use App\Domain\Automation\Models\WorkflowExecution;
use App\Domain\Automation\Support\WorkflowRunner;
use App\Models\User;
use App\Shared\Commerce\Application\RecordOutboxEvent;
use App\Shared\Commerce\Models\OutboxEvent;
use App\Shared\Tenancy\TenantContext;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Str;

/**
 * Same fork-based real-concurrency pattern as
 * CustomerGroupMembershipConcurrencyTest (M18) — proves
 * WorkflowRunner::run()'s guarded claim UPDATE actually prevents the
 * race it exists to prevent (spec section 17: "Concurrency"): without
 * it, two workers picking up the same pending WorkflowExecution (e.g.
 * one from the initial dispatch, one from a retry/resume command
 * firing at an unlucky moment) could both run its actions, double-
 * publishing the event a "publish_event" action records.
 */
beforeEach(function () {
    $this->user = User::factory()->create();
    $this->store = createStoreForUser($this->user);

    // Prevents RunWorkflowExecutionJob::dispatch() from auto-executing
    // the trigger below under phpunit.xml's QUEUE_CONNECTION=sync, which
    // would otherwise run (and complete) the execution here in the
    // parent process before the forked workers below ever get a chance
    // to race on a genuinely pending row.
    Queue::fake();

    $this->executionId = app(TenantContext::class)->scope($this->store, function () {
        $workflow = app(CreateWorkflow::class)->handle([
            'name' => 'Concurrency Test Workflow',
            'trigger' => ['event_type' => 'ConcurrencyTrigger'],
            'conditions' => [],
            'actions' => [['type' => 'publish_event', 'config' => ['event_type' => 'ConcurrencyFired']]],
        ]);
        app(PublishWorkflow::class)->handle($workflow);

        $event = app(RecordOutboxEvent::class)->handle('ConcurrencyTrigger', 'Customer', (string) Str::ulid(), []);
        app(DispatchWorkflowTriggersForEvent::class)->handle($event, $this->store);

        return WorkflowExecution::query()->where('workflow_id', $workflow->id)->firstOrFail()->id;
    });
});

afterEach(function () {
    DB::table('stores')->where('id', $this->store->id)->delete();
    DB::table('users')->where('id', $this->user->id)->delete();
});

it('lets two workers race to run the same pending execution, without double-executing its actions', function () {
    $run = function () {
        return app(TenantContext::class)->scope($this->store, function () {
            app(WorkflowRunner::class)->run($this->executionId);

            return true;
        });
    };

    $results = runConcurrently([$run, $run]);

    $succeeded = array_filter($results, fn ($r) => $r['ok']);
    expect($succeeded)->toHaveCount(2);

    app(TenantContext::class)->scope($this->store, function () {
        $execution = WorkflowExecution::query()->findOrFail($this->executionId);
        expect($execution->status)->toBe(WorkflowExecutionStatus::Completed);

        expect(OutboxEvent::query()->where('event_type', 'ConcurrencyFired')->count())->toBe(1);
        expect($execution->steps()->count())->toBe(2); // one condition step, one action step
    });
});
