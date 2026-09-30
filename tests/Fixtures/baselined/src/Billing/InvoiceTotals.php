<?php

declare(strict_types=1);

namespace App\Billing;

final class InvoiceTotals
{
    /**
     * @param  list<array{price: int, quantity: int, taxable: bool}>  $lines
     */
    public function total(array $lines, int $taxRate): int
    {
        $subtotal = 0;
        $tax = 0;

        foreach ($lines as $line) {
            $amount = $line['price'] * $line['quantity'];
            $subtotal += $amount;

            if ($line['taxable']) {
                $tax += intdiv($amount * $taxRate, 100);
            }
        }

        if ($subtotal > 100000) {
            $subtotal -= intdiv($subtotal, 20);
        }

        return $subtotal + $tax;
    }
}
