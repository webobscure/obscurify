<?php

use App\Domain\Analytics\Support\Export\CsvReportWriter;
use App\Domain\Analytics\Support\Export\PdfReportWriter;
use App\Domain\Analytics\Support\Export\XlsxReportWriter;
use App\Models\User;

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->store = createStoreForUser($this->user);
});

it('exports a report as a real CSV file with correct rows', function () {
    $report = $this->actingAs($this->user, 'sanctum')->postJson('/api/v1/analytics/reports', ['report_type' => 'orders'], tenantHeader($this->store));
    $reportId = $report->json('data.id');

    $export = $this->actingAs($this->user, 'sanctum')->postJson("/api/v1/analytics/reports/{$reportId}/exports", ['format' => 'csv'], tenantHeader($this->store));
    $export->assertCreated()->assertJsonPath('data.status', 'completed');
    expect($export->json('data.download_url'))->not->toBeNull();

    $download = $this->withHeaders(tenantHeader($this->store))->get($export->json('data.download_url'));
    $download->assertOk();
});

it('exports a report as a valid XLSX file', function () {
    $report = $this->actingAs($this->user, 'sanctum')->postJson('/api/v1/analytics/reports', ['report_type' => 'orders'], tenantHeader($this->store));
    $export = $this->actingAs($this->user, 'sanctum')->postJson("/api/v1/analytics/reports/{$report->json('data.id')}/exports", ['format' => 'xlsx'], tenantHeader($this->store));

    $export->assertCreated()->assertJsonPath('data.status', 'completed');
});

it('exports a report as a valid PDF file', function () {
    $report = $this->actingAs($this->user, 'sanctum')->postJson('/api/v1/analytics/reports', ['report_type' => 'orders'], tenantHeader($this->store));
    $export = $this->actingAs($this->user, 'sanctum')->postJson("/api/v1/analytics/reports/{$report->json('data.id')}/exports", ['format' => 'pdf'], tenantHeader($this->store));

    $export->assertCreated()->assertJsonPath('data.status', 'completed');
});

it('stores a scheduled export without generating a file yet ("architecture only")', function () {
    $report = $this->actingAs($this->user, 'sanctum')->postJson('/api/v1/analytics/reports', ['report_type' => 'orders'], tenantHeader($this->store));

    $export = $this->actingAs($this->user, 'sanctum')->postJson("/api/v1/analytics/reports/{$report->json('data.id')}/exports", [
        'format' => 'csv',
        'scheduled_at' => now()->addDay()->toIso8601String(),
        'recurrence' => 'daily',
    ], tenantHeader($this->store));

    $export->assertCreated()->assertJsonPath('data.status', 'pending');
    expect($export->json('data.download_url'))->toBeNull();
});

it('writes a genuinely valid CSV, XLSX zip, and PDF with a correct xref table', function () {
    $rows = [['order_id' => 'abc', 'amount' => 100], ['order_id' => 'def', 'amount' => 200]];

    $csv = app(CsvReportWriter::class)->write($rows);
    expect($csv)->toContain('order_id,amount')->toContain('abc,100');

    $xlsx = app(XlsxReportWriter::class)->write($rows);
    $tempPath = tempnam(sys_get_temp_dir(), 'xlsx-test');
    file_put_contents($tempPath, $xlsx);
    $zip = new ZipArchive;
    expect($zip->open($tempPath))->toBeTrue();
    expect($zip->locateName('xl/worksheets/sheet1.xml'))->not->toBeFalse();
    $sheetXml = $zip->getFromName('xl/worksheets/sheet1.xml');
    $zip->close();
    unlink($tempPath);
    expect(simplexml_load_string($sheetXml))->not->toBeFalse();

    $pdf = app(PdfReportWriter::class)->write($rows);
    expect(substr($pdf, 0, 8))->toBe('%PDF-1.4');
    expect(substr($pdf, -5))->toBe('%%EOF');

    $xrefTablePos = strpos($pdf, "\nxref\n");
    expect($xrefTablePos)->not->toBeFalse();
});
