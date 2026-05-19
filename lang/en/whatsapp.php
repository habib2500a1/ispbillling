<?php

return [
    'menu' => "ISP Bot menu:\n1) BALANCE — account balance\n2) BILL — latest due invoice\n3) PAY — payment link\n4) TICKET — open tickets\n5) PACKAGES — plans & prices\n6) SUPPORT <message> — open ticket\n7) MENU — this menu",
    'unknown_customer' => 'We could not find your number. Reply SUPPORT <issue> and our team will contact you.',
    'balance' => 'Balance: :amount BDT (code :code)',
    'no_due' => 'No unpaid invoice. Thank you!',
    'due' => "Due invoice #:number\nAmount: :amount BDT\nDue: :due\nPay: :url",
    'ticket_opened' => 'Ticket #:number opened. We will reply soon.',
    'support_usage' => 'Send: SUPPORT then your problem description.',
    'help' => 'Send MENU for options.',
    'pay_link' => "Pay invoice #:number\nAmount: :amount BDT\n:url",
    'packages_header' => 'Our packages:',
    'no_packages' => 'No packages listed right now. Call our office.',
    'tickets_header' => 'Your tickets:',
    'no_tickets' => 'No open tickets.',
    'lead_recorded' => 'Request recorded. Our team will contact you soon.',
];
