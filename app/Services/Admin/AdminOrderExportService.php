<?php

namespace App\Services\Admin;

use App\Models\Order;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\File;
use Mpdf\Mpdf;
use Mpdf\Output\Destination;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

/** Produce private downloads from the same validated order query used by the list. */
class AdminOrderExportService
{
    private const HEADERS = [
        'Order number', 'Customer', 'Phone', 'Status', 'Fulfilment', 'Payment status', 'Total (INR)', 'Placed at',
    ];

    /** Return a formula-safe UTF-8 CSV or an actual paginated Unicode PDF attachment. */
    public function download(Builder $orders, array $filters): Response|StreamedResponse
    {
        $orders->withoutEagerLoads();
        if (($filters['format'] ?? 'csv') === 'pdf') {
            return $this->pdf($orders, $filters);
        }

        return response()->streamDownload(function () use ($orders): void {
            $output = fopen('php://output', 'wb');
            fwrite($output, "\xEF\xBB\xBF");
            fputcsv($output, self::HEADERS, ',', '"', '');

            foreach ($orders->cursor() as $order) {
                fputcsv($output, array_map($this->spreadsheetCell(...), $this->row($order)), ',', '"', '');
            }

            fclose($output);
        }, 'dwarkesh-orders.csv', [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Cache-Control' => 'private, no-store',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }

    /** Neutralize spreadsheet formulas even after leading whitespace and preserve phone digits. */
    public function spreadsheetCell(mixed $value): string
    {
        $cell = (string) ($value ?? '');

        return preg_match('/^[\s\x{FEFF}]*[=+@\-]/u', $cell)
            || preg_match('/^[\t\r\n]/', $cell)
            ? "'".$cell
            : $cell;
    }

    /** Map explicit customer-facing report columns without exposing the full order model. */
    private function row(Order $order): array
    {
        return [
            $order->order_number,
            $order->customer_name,
            $order->customer_mobile_number,
            $order->order_status,
            $order->fulfillment_type,
            $order->payment_status,
            number_format((float) $order->grand_total, 2, '.', ''),
            $order->placed_at?->toIso8601String(),
        ];
    }

    /** Render bounded table batches with repeated headings, filters and page numbers. */
    private function pdf(Builder $orders, array $filters): Response
    {
        $directory = storage_path('app/private/order-pdf-cache');
        File::ensureDirectoryExists($directory);
        $pdf = new Mpdf([
            'tempDir' => $directory,
            'format' => 'A4-L',
            'default_font' => 'freeserif',
            'default_font_size' => 9,
            'autoScriptToLang' => true,
            'autoLangToFont' => true,
        ]);
        $pdf->SetTitle('Dwarkesh Bakers orders');
        $pdf->SetAuthor('Dwarkesh Bakers');
        $pdf->SetHTMLFooter('<div style="text-align:right">Page {PAGENO} / {nbpg}</div>');
        $pdf->WriteHTML('<h1>Dwarkesh Bakers — Orders</h1><p>Generated: '.e(now()->toDateTimeString()).'</p>');
        $labels = collect($filters)->except(['page', 'per_page', 'format'])
            ->map(fn ($value, $key) => e(str_replace('_', ' ', $key)).': '.e((string) $value))
            ->implode(' &nbsp; | &nbsp; ');
        $pdf->WriteHTML('<p>'.$labels.'</p>');
        $heading = '<table style="width:100%;border-collapse:collapse"><thead><tr>';
        foreach (self::HEADERS as $label) {
            $heading .= '<th style="border:1px solid #cbd5e1;background:#ecfdf5;padding:6px">'.e($label).'</th>';
        }
        $heading .= '</tr></thead><tbody>';
        $batch = '';
        $count = 0;
        foreach ($orders->cursor() as $order) {
            $batch .= '<tr>';
            foreach ($this->row($order) as $cell) {
                $batch .= '<td style="border:1px solid #cbd5e1;padding:6px">'.e((string) $cell).'</td>';
            }
            $batch .= '</tr>';
            $count++;
            if ($count % 25 === 0) {
                $pdf->WriteHTML($heading.$batch.'</tbody></table>');
                $batch = '';
            }
        }
        if ($batch !== '') {
            $pdf->WriteHTML($heading.$batch.'</tbody></table>');
        }
        if ($count === 0) {
            $pdf->WriteHTML('<p>No orders match these filters.</p>');
        }

        return response($pdf->Output('', Destination::STRING_RETURN), 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="dwarkesh-orders.pdf"',
            'Cache-Control' => 'private, no-store',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }
}
