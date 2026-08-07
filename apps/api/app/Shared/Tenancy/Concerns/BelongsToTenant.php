<?php

namespace App\Shared\Tenancy\Concerns;

use App\Shared\Tenancy\TenantContext;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Scope;

/**
 * Applies mandatory tenant scoping to a model.
 *
 * Every query built through this model (index, find, findOrFail, route
 * model binding, update, delete — all of them go through Eloquent's
 * newQuery(), which applies global scopes) is automatically constrained
 * to the active tenant, and fails closed if no tenant is active.
 *
 * `store_id` is intentionally never fillable: ownership is always forced
 * from TenantContext on creation, never trusted from client input.
 */
trait BelongsToTenant
{
    public static function bootBelongsToTenant(): void
    {
        static::addGlobalScope(new class implements Scope
        {
            public function apply(Builder $builder, Model $model): void
            {
                $tenant = app(TenantContext::class);

                $builder->where($model->qualifyColumn('store_id'), $tenant->storeId());
            }
        });

        static::creating(function (Model $model): void {
            $model->setAttribute('store_id', app(TenantContext::class)->storeId());
        });
    }
}
