<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Models\Delivery;
use App\Models\SalesOrder;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * สร้าง/แก้ไขใบส่งของ
 *
 * ตอนสร้าง route ผูก {sales_order} ตอนแก้ไข route ผูก {delivery}
 * จึงต้องดูทั้งสองตัวว่ามาจากทางไหน
 */
class DeliveryRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();

        if ($user === null) {
            return false;
        }

        $delivery = $this->route('delivery');

        if ($delivery instanceof Delivery) {
            // แก้ไขใบเดิม — สถานะที่ post แล้วตกไปเป็น 409 จาก service (ADR-014)
            return $user->can('updateAny', $delivery);
        }

        $order = $this->route('sales_order');

        return $order instanceof SalesOrder && $user->can('create', Delivery::class);
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'warehouse_id' => ['required', 'integer', Rule::exists('warehouses', 'id')],
            'delivery_date' => ['required', 'date'],
            'receiver_name' => ['nullable', 'string', 'max:255'],
            'vehicle_note' => ['nullable', 'string', 'max:255'],
            'note' => ['nullable', 'string', 'max:2000'],

            'items' => ['required', 'array', 'min:1', 'max:200'],
            'items.*.sales_order_item_id' => [
                'required', 'integer',
                // บรรทัดต้องเป็นของใบสั่งขายใบนี้เท่านั้น กันการยัด id ของใบอื่นเข้ามา
                Rule::exists('sales_order_items', 'id')->where('sales_order_id', $this->salesOrderId()),
            ],
            'items.*.qty' => ['required', 'numeric', 'gt:0', 'max:999999999'],
            'items.*.lot_no' => ['nullable', 'string', 'max:50'],
            'items.*.serial_numbers' => ['nullable', 'string', 'max:20000'],
        ];
    }

    public function salesOrderId(): int
    {
        $delivery = $this->route('delivery');

        if ($delivery instanceof Delivery) {
            return $delivery->sales_order_id;
        }

        $order = $this->route('sales_order');

        return $order instanceof SalesOrder ? $order->id : 0;
    }

    /** @return array<string, string> */
    public function attributes(): array
    {
        return [
            'warehouse_id' => __('คลังที่จ่ายของ'),
            'delivery_date' => __('วันที่ส่ง'),
            'receiver_name' => __('ชื่อผู้รับ'),
            'items' => __('รายการที่ส่ง'),
            'items.*.qty' => __('จำนวน'),
            'items.*.serial_numbers' => __('Serial'),
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'items.required' => __('ต้องมีรายการที่ส่งอย่างน้อย 1 บรรทัด'),
            'items.*.qty.gt' => __('จำนวนต้องมากกว่า 0'),
            'items.*.sales_order_item_id.exists' => __('บรรทัดนี้ไม่ได้อยู่ในใบสั่งขายที่ระบุ'),
        ];
    }
}
