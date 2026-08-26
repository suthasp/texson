<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Customer;
use App\Models\CustomerContact;
use App\Models\CustomerSite;
use Illuminate\Support\Facades\DB;

class CustomerService
{
    /**
     * @param  array<string, mixed>  $data
     */
    public function create(array $data): Customer
    {
        return Customer::create($data);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function update(Customer $customer, array $data): Customer
    {
        $customer->update($data);

        return $customer->refresh();
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function addContact(Customer $customer, array $data): CustomerContact
    {
        return DB::transaction(function () use ($customer, $data): CustomerContact {
            $contact = $customer->contacts()->create($data);

            if ($contact->is_primary) {
                $this->demoteOtherPrimaryContacts($customer, $contact->id);
            }

            return $contact;
        });
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function updateContact(CustomerContact $contact, array $data): CustomerContact
    {
        return DB::transaction(function () use ($contact, $data): CustomerContact {
            $contact->update($data);

            if ($contact->is_primary) {
                $this->demoteOtherPrimaryContacts($contact->customer, $contact->id);
            }

            return $contact->refresh();
        });
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function addSite(Customer $customer, array $data): CustomerSite
    {
        return $customer->sites()->create($data);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function updateSite(CustomerSite $site, array $data): CustomerSite
    {
        $site->update($data);

        return $site->refresh();
    }

    /**
     * ผู้ติดต่อหลักมีได้รายเดียวต่อลูกค้า — ตั้งคนใหม่แล้วคนเก่าต้องถูกปลด
     */
    private function demoteOtherPrimaryContacts(Customer $customer, int $keepContactId): void
    {
        $customer->contacts()
            ->whereKeyNot($keepContactId)
            ->where('is_primary', true)
            ->update(['is_primary' => false]);
    }
}
