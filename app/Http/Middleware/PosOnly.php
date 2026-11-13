<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

/**
 * POS-only mode: blocks the back-office features (purchases, returns,
 * quotations, expenses, stock adjustments, suppliers, most reports).
 *
 * The modules themselves stay installed so their database tables and
 * classes still exist (other code references them). To re-enable a
 * feature, remove its prefix from the list below and restore its menu item.
 */
class PosOnly
{
    protected array $blocked = [
        'adjustments*',
        'quotations*', 'quotation/*', 'quotation-sales*',
        'purchases*', 'purchase-payments*',
        'purchase-returns*', 'purchase-return-payments*',
        'sale-returns*', 'sale-return-payments*',
        'expenses*', 'expense-categories*',
        'suppliers*',
        'profit-loss-report*', 'payments-report*',
        'purchases-report*', 'purchases-return-report*', 'sales-return-report*',
        'sales/create',
    ];

    public function handle(Request $request, Closure $next)
    {
        if ($request->is(...$this->blocked)) {
            abort(404);
        }

        return $next($request);
    }
}
