<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Actions\Admin\WriteAdminAuditLog;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Admin\AdminOperationsActionRequest;
use App\Http\Requests\Api\V1\Admin\StaffRequest;
use App\Models\AdminActivityLog;
use App\Models\Coupon;
use App\Models\Order;
use App\Models\SupportMessage;
use App\Models\SupportTicket;
use App\Models\User;
use App\Traits\ApiResponseTrait;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;

/** Handle admin operations for promotions, support, customer accounts, staff and audit history. */
class AdminOperationsController extends Controller
{
    use ApiResponseTrait;

    /** List active and inactive promotions with redemption counts and paging metadata. */
    public function getCouponList(AdminOperationsActionRequest $request): JsonResponse
    {
        $rows = Coupon::query()->withCount('redemptions')->latest('id')->paginate($request->integer('per_page', 30));

        return $this->successResponse('Coupons loaded.', $rows->through(fn ($c) => [
            'id' => $c->id,
            'code' => $c->code,
            'title_translations' => $c->title_translations,
            'description_translations' => $c->description_translations,
            'discount_type' => $c->discount_type,
            'discount_value' => (float) $c->discount_value,
            'minimum_order_amount' => (float) $c->minimum_order_amount,
            'max_discount_amount' => $c->max_discount_amount ? (float) $c->max_discount_amount : null,
            'usage_limit' => $c->usage_limit,
            'usage_limit_per_customer' => $c->usage_limit_per_customer,
            'applies_to_fulfillment_type' => $c->applies_to_fulfillment_type,
            'starts_at' => $c->starts_at?->toIso8601String(),
            'ends_at' => $c->ends_at?->toIso8601String(),
            'is_active' => $c->is_active,
            'redemptions_count' => $c->redemptions_count,
        ]), [
            'current_page' => $rows->currentPage(),
            'last_page' => $rows->lastPage(),
            'total' => $rows->total(),
        ]);
    }

    /** Create or update a promotion using validated campaign rules. */
    public function saveCoupon(AdminOperationsActionRequest $request, WriteAdminAuditLog $audit): JsonResponse
    {
        $data = $request->validated();
        $id = $data['id'] ?? null;
        unset($data['id']);
        $data['code'] = strtoupper(trim($data['code']));
        $coupon = DB::transaction(fn () => Coupon::query()->updateOrCreate(['id' => $id], $data));
        $audit->execute($request, 'coupon.saved', $coupon);

        return $this->successResponse('Coupon saved.', [
            'id' => $coupon->id,
            'code' => $coupon->code,
            'discount_type' => $coupon->discount_type,
            'discount_value' => (float) $coupon->discount_value,
            'is_active' => $coupon->is_active,
        ]);
    }

    /** Create a coupon using the UI create action. */
    public function createCoupon(AdminOperationsActionRequest $request, WriteAdminAuditLog $audit): JsonResponse
    {
        return $this->saveCoupon($request, $audit);
    }

    /** Update an existing coupon using its id. */
    public function updateCoupon(AdminOperationsActionRequest $request, WriteAdminAuditLog $audit): JsonResponse
    {
        return $this->saveCoupon($request, $audit);
    }

    /** Enable or disable a coupon while preserving redemption history. */
    public function updateCouponStatus(AdminOperationsActionRequest $request, WriteAdminAuditLog $audit): JsonResponse
    {
        $d = $request->validated();
        $coupon = Coupon::query()->findOrFail($d['id']);
        $coupon->is_active = $d['is_active'];
        $coupon->save();
        $audit->execute($request, 'coupon.status_changed', $coupon);

        return $this->successResponse('Coupon status updated.', ['id' => $coupon->id, 'is_active' => $coupon->is_active]);
    }

    /** Soft-delete a coupon while keeping historical order references intact. */
    public function deleteCoupon(AdminOperationsActionRequest $request, WriteAdminAuditLog $audit): JsonResponse
    {
        $coupon = Coupon::query()->findOrFail($request->validated()['id']);
        $coupon->delete();
        $audit->execute($request, 'coupon.deleted', $coupon);

        return $this->successResponse('Coupon deleted.', []);
    }

    /** List support tickets with customer and latest message preview for the inbox. */
    public function getSupportTicketList(AdminOperationsActionRequest $request): JsonResponse
    {
        $rows = SupportTicket::query()->with([
            'customer:id,name,mobile_number',
            'messages' => fn ($q) => $q->latest()->limit(1),
        ])->when($request->filled('status'), fn ($q) => $q->where('status', $request->string('status')))->latest()->paginate($request->integer('per_page', 30));

        return $this->successResponse('Support tickets loaded.', $rows->through(fn ($t) => [
            'id' => $t->id,
            'ticket_number' => $t->ticket_number,
            'subject' => $t->subject,
            'status' => $t->status,
            'priority' => $t->priority,
            'customer_name' => $t->customer?->name,
            'customer_phone' => $t->customer?->mobile_number,
            'message' => $t->messages->first()?->message,
            'created_at' => $t->created_at?->toIso8601String(),
        ]), [
            'current_page' => $rows->currentPage(),
            'last_page' => $rows->lastPage(),
            'total' => $rows->total(),
        ]);
    }

    /** Load a support ticket conversation using the numeric identifier used by the portal. */
    public function getSupportTicketDetail(AdminOperationsActionRequest $request): JsonResponse
    {
        $validated = $request->validated();
        $ticket = $this->findTicket($validated);
        $ticket->load(['customer:id,name,mobile_number', 'messages']);
        $messages = $ticket->messages;

        $ticketDetails = [
            'id' => $ticket->id,
            'ticket_number' => $ticket->ticket_number,
            'subject' => $ticket->subject,
            'status' => $ticket->status,
            'priority' => $ticket->priority,
            'customer_name' => $ticket->customer?->name,
            'customer_phone' => $ticket->customer?->mobile_number,
            'message' => $messages->first()?->message,
            'replies' => $messages->skip(1)->map(fn ($message) => [
                'id' => $message->id,
                'message' => $message->message,
                'is_admin' => $message->sender_type === 'admin',
                'created_at' => $message->created_at?->toIso8601String(),
            ]),
        ];

        return $this->successResponse('Support conversation loaded.', $ticketDetails);
    }

    /** Add an admin response and update optional ticket state/assignment in one transaction. */
    public function replyToSupportTicket(AdminOperationsActionRequest $request, WriteAdminAuditLog $audit): JsonResponse
    {
        $data = $request->validated();
        $ticket = $this->findTicket($data);
        DB::transaction(function () use ($request, $data, $ticket) {
            SupportMessage::query()->create([
                'support_ticket_id' => $ticket->id,
                'sender_user_id' => $request->user()->id,
                'sender_type' => 'admin',
                'message' => $data['message'],
            ]);
            foreach (['status', 'priority', 'assigned_to_user_id'] as $field) {
                if (array_key_exists($field, $data)) {
                    $ticket->{$field} = $data[$field];
                }
            }

            $ticket->save();
        });
        $audit->execute($request, 'support.ticket_replied', $ticket);

        return $this->successResponse('Support reply sent.', ['id' => $ticket->id, 'status' => $ticket->status]);
    }

    /** Route alias for replying to the currently selected ticket. */
    public function replyTicket(AdminOperationsActionRequest $request, WriteAdminAuditLog $audit): JsonResponse
    {
        return $this->replyToSupportTicket($request, $audit);
    }

    /** Change ticket state without creating a conversation message. */
    public function updateTicketStatus(AdminOperationsActionRequest $request, WriteAdminAuditLog $audit): JsonResponse
    {
        $d = $request->validated();
        $ticket = $this->findTicket($d);
        $ticket->status = $d['status'];
        $ticket->save();
        $audit->execute($request, 'support.ticket_status_changed', $ticket, ['status' => $ticket->status]);

        return $this->successResponse('Ticket status updated.', ['id' => $ticket->id, 'status' => $ticket->status]);
    }

    /** List customer accounts with a count query and a minimal identity payload. */
    public function getCustomerList(AdminOperationsActionRequest $request): JsonResponse
    {
        $query = User::query()
            ->select('users.*')
            ->selectSub(
                Order::query()
                    ->selectRaw('count(*)')
                    ->whereColumn('orders.user_id', 'users.id'),
                'orders_count'
            )
            ->where('role', 'customer')
            ->when(
                $request->filled('search'),
                fn ($query) => $query->where(function ($searchQuery) use ($request) {
                    $searchTerm = '%'.$request->string('search').'%';

                    $searchQuery->where('name', 'like', $searchTerm)
                        ->orWhere('mobile_number', 'like', $searchTerm);
                })
            )
            ->latest();
        $rows = $query->paginate($request->integer('per_page', 30));

        return $this->successResponse('Customers loaded.', $rows->through(fn ($u) => [
            'id' => $u->id,
            'name' => $u->name,
            'mobile_number' => $u->mobile_number,
            'email' => $u->email,
            'avatar_url' => $u->avatar_path ? Storage::disk(config('imagepath.disk'))->url($u->avatar_path) : null,
            'is_active' => $u->is_active,
            'orders_count' => (int) $u->orders_count,
            'created_at' => $u->created_at?->toIso8601String(),
        ]), [
            'current_page' => $rows->currentPage(),
            'last_page' => $rows->lastPage(),
            'total' => $rows->total(),
        ]);
    }

    /** Activate or suspend a customer account without allowing role changes. */
    public function updateCustomerStatus(AdminOperationsActionRequest $request, WriteAdminAuditLog $audit): JsonResponse
    {
        $d = $request->validated();
        $user = User::query()->where('role', 'customer')->findOrFail($d['customer_id']);
        $user->is_active = $d['is_active'];
        $user->save();
        $audit->execute($request, 'customer.status_changed', $user, ['is_active' => $user->is_active]);

        return $this->successResponse('Customer status updated.', ['id' => $user->id, 'is_active' => $user->is_active]);
    }

    /** Create or update a least-privilege staff account and hash any supplied password. */
    public function saveStaff(StaffRequest $request, WriteAdminAuditLog $audit): JsonResponse
    {
        $d = $request->validated();
        $id = $d['id'] ?? null;
        unset($d['id']);
        $user = DB::transaction(function () use ($d, $id) {
            $user = $id ? User::query()->whereIn('role', ['admin', 'staff', 'delivery'])->findOrFail($id) : new User;
            if (! empty($d['password'])) {
                $d['password'] = Hash::make($d['password']);
            } else {
                unset($d['password']);
            }

            $user->fill($d)->save();

            return $user;
        });
        $audit->execute($request, 'staff.saved', $user, ['role' => $user->role]);

        return $this->successResponse('Staff account saved.', [
            'id' => $user->id,
            'name' => $user->name,
            'mobile_number' => $user->mobile_number,
            'email' => $user->email,
            'role' => $user->role,
            'is_active' => $user->is_active,
        ]);
    }

    /** Create a back-office account. */
    public function createStaff(StaffRequest $request, WriteAdminAuditLog $audit): JsonResponse
    {
        return $this->saveStaff($request, $audit);
    }

    /** Update a back-office account. */
    public function updateStaff(StaffRequest $request, WriteAdminAuditLog $audit): JsonResponse
    {
        return $this->saveStaff($request, $audit);
    }

    /** List staff identities and their last successful sign-in time. */
    public function getStaffList(): JsonResponse
    {
        return $this->successResponse('Staff loaded.', User::query()->whereIn('role', [
            'owner',
            'admin',
            'staff',
            'delivery',
        ])->orderBy('role')->orderBy('name')->get([
            'id',
            'name',
            'mobile_number',
            'email',
            'role',
            'is_active',
            'last_login_at',
        ])->map(fn ($u) => [
            'id' => $u->id,
            'name' => $u->name,
            'mobile_number' => $u->mobile_number,
            'email' => $u->email,
            'role' => $u->role,
            'is_active' => $u->is_active,
            'last_login_at' => $u->last_login_at?->toIso8601String(),
        ]));
    }

    /** Enable or suspend a staff account, while protecting the owner account. */
    public function updateStaffStatus(AdminOperationsActionRequest $request, WriteAdminAuditLog $audit): JsonResponse
    {
        $d = $request->validated();
        $staff = User::query()->whereIn('role', ['admin', 'staff', 'delivery'])->findOrFail($d['staff_id']);
        $staff->is_active = $d['is_active'];
        $staff->save();
        $audit->execute($request, 'staff.status_changed', $staff, ['is_active' => $staff->is_active]);

        return $this->successResponse('Staff status updated.', ['id' => $staff->id, 'is_active' => $staff->is_active]);
    }

    /** Return paginated audit events and actor labels without leaking token or request secrets. */
    public function getActivityLog(AdminOperationsActionRequest $request): JsonResponse
    {
        $rows = AdminActivityLog::query()->with('user:id,name,role')->latest()->paginate($request->integer('per_page', 50));

        return $this->successResponse('Activity log loaded.', $rows->through(fn ($a) => [
            'id' => $a->id,
            'actor' => $a->user?->name,
            'action' => $a->action,
            'subject_type' => $a->subject_type,
            'subject_id' => $a->subject_id,
            'properties' => $a->properties_json,
            'created_at' => $a->created_at?->toIso8601String(),
        ]), [
            'current_page' => $rows->currentPage(),
            'last_page' => $rows->lastPage(),
            'total' => $rows->total(),
        ]);
    }

    /** Resolve a ticket by either stable numeric id or the public support reference. */
    private function findTicket(array $data): SupportTicket
    {
        if (isset($data['id'])) {
            return SupportTicket::query()->findOrFail($data['id']);
        }

        return SupportTicket::query()
            ->where('ticket_number', $data['ticket_number'])
            ->firstOrFail();
    }
}
