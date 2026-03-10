<?php

namespace ProPhoto\Invoicing\Policies;

use ProPhoto\Access\Enums\UserRole;
use ProPhoto\Access\Permissions;
use ProPhoto\Invoicing\Models\Invoice;

class InvoicePolicy
{
    /**
     * Determine whether the user can view any invoices.
     */
    public function viewAny($user): bool
    {
        return $user->hasRole(UserRole::STUDIO_USER->value)
            || $user->hasRole(UserRole::CLIENT_USER->value);
    }

    /**
     * Determine whether the user can view the invoice.
     */
    public function view($user, Invoice $invoice): bool
    {
        // Studio users see all
        if ($user->hasRole(UserRole::STUDIO_USER->value)) {
            return true;
        }

        // Client users see their organization's invoices
        if ($user->hasRole(UserRole::CLIENT_USER->value)) {
            return $user->organization_id === $invoice->organization_id
                && $user->hasPermissionTo(Permissions::VIEW_INVOICES);
        }

        return false;
    }

    /**
     * Determine whether the user can create invoices.
     */
    public function create($user): bool
    {
        return $user->hasRole(UserRole::STUDIO_USER->value);
    }

    /**
     * Determine whether the user can update the invoice.
     */
    public function update($user, Invoice $invoice): bool
    {
        return $user->hasRole(UserRole::STUDIO_USER->value);
    }

    /**
     * Determine whether the user can delete the invoice.
     */
    public function delete($user, Invoice $invoice): bool
    {
        return $user->hasRole(UserRole::STUDIO_USER->value);
    }

    /**
     * Determine whether the user can send the invoice.
     */
    public function send($user, Invoice $invoice): bool
    {
        return $user->hasRole(UserRole::STUDIO_USER->value);
    }

    /**
     * Determine whether the user can record payment.
     */
    public function recordPayment($user, Invoice $invoice): bool
    {
        return $user->hasRole(UserRole::STUDIO_USER->value);
    }

    /**
     * Determine whether the user can download the invoice PDF.
     */
    public function downloadPdf($user, Invoice $invoice): bool
    {
        if ($user->hasRole(UserRole::STUDIO_USER->value)) {
            return true;
        }

        if ($user->hasRole(UserRole::CLIENT_USER->value)) {
            return $user->organization_id === $invoice->organization_id
                && $user->hasPermissionTo(Permissions::DOWNLOAD_INVOICE_PDF);
        }

        return false;
    }
}
