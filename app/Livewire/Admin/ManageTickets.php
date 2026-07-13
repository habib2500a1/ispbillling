<?php

namespace App\Livewire\Admin;

use App\Models\CustomersInfo;
use App\Models\NotificationLogs;
use App\Models\SupportTicket;
use App\Services\CallCenter\CallDeskService;
use Codepagol\SmsBridge\Facades\SmsBridge;
use Livewire\Attributes\On;
use Livewire\Component;
use Livewire\WithoutUrlPagination;
use Livewire\WithPagination;

class ManageTickets extends Component
{
    use WithoutUrlPagination, WithPagination;

    public $search = '';

    public $statusFilter = '';

    public $priorityFilter = '';

    public $perPage = 10;

    public $ticketId;

    public $adminReply = '';

    public $status = 'open';

    public $confirmingReply = false;

    public $selectedTicket = null;

    public bool $sendSms = true;

    public bool $confirmingCreate = false;

    public string $customerSearch = '';

    public ?string $newCustomerUid = null;

    public string $newSubject = '';

    public string $newDescription = '';

    public string $newPriority = 'medium';

    public string $newCategory = 'connection';

    /** @var list<array<string, mixed>> */
    public array $customerSearchResults = [];

    public function mount()
    {
        if (! hasAccess(['Super Admin'], ['manage-tickets', 'view-tickets'])) {
            abort(403, 'Unauthorized action.');
        }
    }

    public function showCreateModal(): void
    {
        $this->resetCreateForm();
        $this->confirmingCreate = true;
    }

    public function updatedCustomerSearch(): void
    {
        $this->customerSearchResults = app(CallDeskService::class)
            ->searchCustomers($this->customerSearch, 10);
    }

    public function selectCustomerForTicket(string $uid): void
    {
        $this->newCustomerUid = $uid;
        $customer = CustomersInfo::query()
            ->with('pppUser:id,username')
            ->where('customer_unique_id', $uid)
            ->whereNull('deleted_at')
            ->first();

        if ($customer) {
            $this->customerSearch = trim($customer->customer_name.' ('.$customer->customer_unique_id.')');
        }
        $this->customerSearchResults = [];
    }

    public function createTicket(): void
    {
        $this->validate([
            'newCustomerUid' => 'required|string|exists:customers_infos,customer_unique_id',
            'newSubject' => 'required|string|min:5|max:255',
            'newDescription' => 'required|string|min:10|max:2000',
            'newPriority' => 'required|in:'.implode(',', SupportTicket::PRIORITIES),
            'newCategory' => 'required|in:'.implode(',', SupportTicket::CATEGORIES),
        ]);

        $customer = CustomersInfo::query()
            ->with('pppUser:id,username')
            ->where('customer_unique_id', $this->newCustomerUid)
            ->whereNull('deleted_at')
            ->firstOrFail();

        $ticket = SupportTicket::create([
            'ticket_no' => SupportTicket::generateTicketNo(),
            'customer_unique_id' => $customer->customer_unique_id,
            'ppp_username' => $customer->pppUser?->username,
            'subject' => $this->newSubject,
            'description' => $this->newDescription,
            'priority' => $this->newPriority,
            'category' => $this->newCategory,
            'status' => 'open',
        ]);

        NotificationLogs::create([
            'title' => 'New Support Ticket Opened',
            'message' => 'Admin opened support ticket #'.$ticket->ticket_no.' for '.$customer->customer_name.' ('.$customer->customer_unique_id.'): '.$ticket->subject,
            'status' => 'New Ticket Created',
            'type' => 'Support Ticket',
        ]);

        flash()->success('Support ticket opened successfully.');
        $this->confirmingCreate = false;
        $this->resetCreateForm();
        $this->statusFilter = 'open';
        $this->resetPage();
    }

    protected function resetCreateForm(): void
    {
        $this->customerSearch = '';
        $this->newCustomerUid = null;
        $this->newSubject = '';
        $this->newDescription = '';
        $this->newPriority = 'medium';
        $this->newCategory = 'connection';
        $this->customerSearchResults = [];
        $this->resetValidation();
    }

    public function showReplyModal($id)
    {
        $this->ticketId = $id;
        $this->selectedTicket = SupportTicket::with('customer')->findOrFail($id);
        $this->adminReply = $this->selectedTicket->admin_reply ?? '';
        $this->status = $this->selectedTicket->status;
        $this->sendSms = true;
        $this->confirmingReply = true;
    }

    public function submitReply()
    {
        $this->validate([
            'adminReply' => 'required|string|min:5|max:2000',
            'status' => 'required|in:open,in_progress,resolved,closed',
        ]);

        $ticket = SupportTicket::with('customer')->findOrFail($this->ticketId);
        $ticket->update([
            'admin_reply' => $this->adminReply,
            'status' => $this->status,
            'replied_at' => now(),
            'replied_by' => auth()->user()->name,
        ]);

        // Create notification log about reply
        NotificationLogs::create([
            'title' => 'Support Ticket Replied',
            'message' => "Admin has replied to ticket #{$ticket->ticket_no} ({$ticket->subject}) and marked status as ".ucfirst(str_replace('_', ' ', $this->status)),
            'status' => 'replied',
            'type' => 'Support Ticket',
        ]);

        flash()->success('Ticket reply submitted and status updated.');
        $this->confirmingReply = false;

        // Prompt SweetAlert to send SMS if customer has a mobile number
        if ($ticket->customer && $ticket->customer->mobile) {
            sweetalert()
                ->option('confirmButtonText', 'Yes')
                ->option('denyButtonText', 'No')
                ->option('allowOutsideClick', false)
                ->option('allowEscapeKey', false)
                ->option('timer', null)
                ->option('timerProgressBar', false)
                ->showDenyButton()
                ->info("Would you like to send an SMS reply to the customer ({$ticket->customer->customer_name})?");
        } else {
            $this->reset(['ticketId', 'adminReply', 'status', 'selectedTicket', 'sendSms']);
        }
    }

    #[On('sweetalert:confirmed')]
    public function onConfirmed(array $payload): void
    {
        if ($this->ticketId) {
            $ticket = SupportTicket::with('customer')->find($this->ticketId);
            if ($ticket && $ticket->customer && $ticket->customer->mobile) {
                $mobile = $ticket->customer->mobile;
                $replySnippet = mb_strlen($ticket->admin_reply) > 100
                    ? mb_substr($ticket->admin_reply, 0, 97).'...'
                    : $ticket->admin_reply;

                $message = "Dear {$ticket->customer->customer_name}, Support Ticket #{$ticket->ticket_no} has a new response: \"{$replySnippet}\". Status: ".ucfirst(str_replace('_', ' ', $ticket->status)).'. Regards, '.siteUrlSettings('site_name');

                try {
                    $response = SmsBridge::to($mobile)
                        ->message($message)
                        ->send();
                    if ($response && $response->isSuccessful()) {
                        flash()->success('SMS notification sent to customer.');
                    } else {
                        $errorMsg = $response ? $response->getMessage() : 'Unknown error';
                        flash()->error('Failed to send SMS: '.$errorMsg);
                    }
                } catch (\Exception $smsEx) {
                    \Log::warning("Failed to send SMS reply for ticket #{$ticket->ticket_no}: ".$smsEx->getMessage());
                    flash()->error('Failed to send SMS notification: '.$smsEx->getMessage());
                }
            }
        }
        $this->reset(['ticketId', 'adminReply', 'status', 'selectedTicket', 'sendSms']);
    }

    #[On('sweetalert:denied')]
    public function onDeny(array $payload): void
    {
        $this->reset(['ticketId', 'adminReply', 'status', 'selectedTicket', 'sendSms']);
    }

    public function render()
    {
        // Stats calculations
        $stats = [
            'total' => SupportTicket::count(),
            'open' => SupportTicket::where('status', 'open')->count(),
            'in_progress' => SupportTicket::where('status', 'in_progress')->count(),
            'resolved' => SupportTicket::where('status', 'resolved')->count(),
        ];

        $query = SupportTicket::with('customer');

        if ($this->search) {
            $query->where(function ($q) {
                $q->where('ticket_no', 'like', '%'.$this->search.'%')
                    ->orWhere('subject', 'like', '%'.$this->search.'%')
                    ->orWhere('ppp_username', 'like', '%'.$this->search.'%')
                    ->orWhereHas('customer', function ($c) {
                        $c->where('customer_name', 'like', '%'.$this->search.'%')
                            ->orWhere('customer_unique_id', 'like', '%'.$this->search.'%');
                    });
            });
        }

        if ($this->statusFilter) {
            $query->where('status', $this->statusFilter);
        }

        if ($this->priorityFilter) {
            $query->where('priority', $this->priorityFilter);
        }

        $tickets = $query->orderByDesc('created_at')->paginate($this->perPage);

        return view('livewire.admin.tickets.manage-tickets', [
            'tickets' => $tickets,
            'stats' => $stats,
        ])->layout('layouts.app');
    }
}
