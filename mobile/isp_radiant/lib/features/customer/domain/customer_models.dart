/// Typed, null-safe models for the customer billing / services area.
/// Backend JSON is unchanged — these only re-shape it so screens stop touching
/// raw `Map<String, dynamic>`.
library;

double _d(dynamic v) => v is num ? v.toDouble() : double.tryParse('${v ?? ''}') ?? 0;
int? _iN(dynamic v) => v is num ? v.toInt() : int.tryParse('${v ?? ''}');
String _s(dynamic v, [String f = '']) {
  final s = v?.toString();
  return (s == null || s.isEmpty) ? f : s;
}

class PaymentRecord {
  const PaymentRecord({
    required this.title,
    required this.paidAt,
    required this.amount,
    required this.reference,
    required this.status,
    required this.invoiceId,
  });

  final String title;
  final String paidAt;
  final double amount;
  final String reference;
  final String status;
  final int? invoiceId;

  factory PaymentRecord.fromJson(Map<String, dynamic> j) => PaymentRecord(
        title: _s(j['title'], 'Monthly Bill'),
        paidAt: _s(j['paid_at']),
        amount: _d(j['amount']),
        reference: _s(j['invoice_number'], _s(j['receipt_number'])),
        status: _s(j['status'], 'Paid'),
        invoiceId: _iN(j['invoice_id']),
      );
}

class DueInvoice {
  const DueInvoice({
    required this.id,
    required this.invoiceNumber,
    required this.dueDate,
    required this.status,
    required this.balanceDue,
  });

  final int id;
  final String invoiceNumber;
  final String dueDate;
  final String status;
  final double balanceDue;

  factory DueInvoice.fromJson(Map<String, dynamic> j) => DueInvoice(
        id: _iN(j['id']) ?? 0,
        invoiceNumber: _s(j['invoice_number'], 'Invoice'),
        dueDate: _s(j['due_date'], '—'),
        status: _s(j['status']),
        balanceDue: _d(j['balance_due']),
      );
}

class PrepayQuote {
  const PrepayQuote({
    required this.months,
    required this.monthlyRate,
    required this.currentDue,
    required this.prepayAmount,
    required this.totalAmount,
    this.projectedExpiresAt,
  });

  final int months;
  final double monthlyRate;
  final double currentDue;
  final double prepayAmount;
  final double totalAmount;
  final String? projectedExpiresAt;

  factory PrepayQuote.fromJson(Map<String, dynamic> j) => PrepayQuote(
        months: _iN(j['months']) ?? 1,
        monthlyRate: _d(j['monthly_rate']),
        currentDue: _d(j['current_due']),
        prepayAmount: _d(j['prepay_amount']),
        totalAmount: _d(j['total_amount']),
        projectedExpiresAt: j['projected_expires_at']?.toString(),
      );
}

class PrepaySection {
  const PrepaySection({
    required this.enabled,
    required this.monthlyRate,
    required this.packageName,
    required this.canPayOnline,
    required this.quickMonths,
    required this.quotes,
  });

  final bool enabled;
  final double monthlyRate;
  final String packageName;
  final bool canPayOnline;
  final List<int> quickMonths;
  final Map<int, PrepayQuote> quotes;

  PrepayQuote? quoteFor(int months) => quotes[months];

  factory PrepaySection.fromJson(Map<String, dynamic>? j) {
    if (j == null || j['enabled'] != true) {
      return const PrepaySection(
        enabled: false,
        monthlyRate: 0,
        packageName: '',
        canPayOnline: false,
        quickMonths: [],
        quotes: {},
      );
    }
    final rawQuotes = j['quotes'] as Map<String, dynamic>? ?? const {};
    final quotes = <int, PrepayQuote>{};
    rawQuotes.forEach((key, value) {
      if (value is Map) {
        quotes[int.parse(key)] = PrepayQuote.fromJson(Map<String, dynamic>.from(value));
      }
    });
    final quick = (j['quick_months'] as List<dynamic>? ?? const [])
        .map((e) => _iN(e) ?? 0)
        .where((m) => m > 0)
        .toList();
    return PrepaySection(
      enabled: true,
      monthlyRate: _d(j['monthly_rate']),
      packageName: _s(j['package_name']),
      canPayOnline: j['can_pay_online'] == true,
      quickMonths: quick,
      quotes: quotes,
    );
  }
}

class Payables {
  const Payables({
    required this.totalDue,
    required this.walletBalance,
    required this.message,
    required this.gateways,
    required this.dueInvoices,
    required this.prepay,
  });

  final double totalDue;
  final double walletBalance;
  final String message;
  final Map<String, bool> gateways;
  final List<DueInvoice> dueInvoices;
  final PrepaySection prepay;

  /// Ordered gateway options the customer can pay with (key → label).
  Map<String, String> get gatewayOptions => {
        if (gateways['bkash'] == true) 'bkash': 'bKash',
        if (gateways['nagad'] == true) 'nagad': 'Nagad',
        if (gateways['rocket'] == true) 'rocket': 'Rocket',
        if (gateways['sslcommerz'] == true) 'sslcommerz': 'Card / SSLCommerz',
        if (gateways['piprapay'] == true) 'piprapay': 'PipraPay',
      };

  factory Payables.fromJson(Map<String, dynamic> j) {
    final g = j['gateways'] as Map<String, dynamic>? ?? const {};
    return Payables(
      totalDue: _d(j['total_due']),
      walletBalance: _d(j['wallet_balance']),
      message: _s(j['message']),
      gateways: g.map((k, v) => MapEntry(k, v == true)),
      dueInvoices: (j['due_invoices'] as List<dynamic>? ?? const [])
          .whereType<Map>()
          .map((e) => DueInvoice.fromJson(Map<String, dynamic>.from(e)))
          .toList(),
      prepay: PrepaySection.fromJson(j['prepay'] as Map<String, dynamic>?),
    );
  }
}

class PackageOption {
  const PackageOption({
    required this.id,
    required this.name,
    required this.downloadMbps,
    required this.uploadMbps,
    required this.priceMonthly,
    required this.isCurrent,
  });

  final int id;
  final String name;
  final String downloadMbps;
  final String uploadMbps;
  final double priceMonthly;
  final bool isCurrent;

  factory PackageOption.fromJson(Map<String, dynamic> j) => PackageOption(
        id: _iN(j['id']) ?? 0,
        name: _s(j['name'], 'Package'),
        downloadMbps: _s(j['download_mbps'], 'N/A'),
        uploadMbps: _s(j['upload_mbps'], 'N/A'),
        priceMonthly: _d(j['price_monthly']),
        isCurrent: j['is_current'] == true,
      );
}

class InvoiceLine {
  const InvoiceLine({required this.title, required this.subtitle, required this.amount});
  final String title;
  final String subtitle;
  final double amount;
}

class InvoiceDetail {
  const InvoiceDetail({
    required this.periodLabel,
    required this.total,
    required this.subtotal,
    required this.previousDue,
    required this.amountPaid,
    required this.balanceDue,
    required this.canPay,
    required this.generationDate,
    required this.expireDate,
    required this.note,
    required this.customerName,
    required this.customerCode,
    required this.server,
    required this.phone,
    required this.payments,
    required this.items,
  });

  final String periodLabel;
  final double total;
  final double subtotal;
  final double previousDue;
  final double amountPaid;
  final double balanceDue;
  final bool canPay;
  final String generationDate;
  final String expireDate;
  final String note;
  final String customerName;
  final String customerCode;
  final String server;
  final String phone;
  final List<InvoiceLine> payments;
  final List<InvoiceLine> items;

  factory InvoiceDetail.fromJson(Map<String, dynamic> j) {
    final inv = j['invoice'] as Map<String, dynamic>? ?? const {};
    final cust = j['customer'] as Map<String, dynamic>? ?? const {};
    List<InvoiceLine> lines(dynamic raw, String titleKey, String subKey) =>
        (raw as List<dynamic>? ?? const [])
            .whereType<Map>()
            .map((e) {
              final m = Map<String, dynamic>.from(e);
              return InvoiceLine(
                title: _s(m[titleKey], titleKey == 'method' ? 'Payment' : 'Item'),
                subtitle: _s(m[subKey] ?? m['paid_at']),
                amount: _d(m['amount'] ?? m['line_total']),
              );
            })
            .toList();
    return InvoiceDetail(
      periodLabel: _s(inv['period_label'], 'Invoice'),
      total: _d(inv['total']),
      subtotal: _d(inv['subtotal']),
      previousDue: _d(inv['previous_due']),
      amountPaid: _d(inv['amount_paid']),
      balanceDue: _d(inv['balance_due']),
      canPay: inv['can_pay'] == true,
      generationDate: _s(inv['generation_date'], '—'),
      expireDate: _s(inv['expire_date'], '—'),
      note: _s(inv['note']),
      customerName: _s(cust['name'], '—'),
      customerCode: _s(cust['customer_code'], '—'),
      server: _s(cust['server'], '—'),
      phone: _s(cust['phone'], '—'),
      payments: lines(inv['payments'], 'method', 'paid_at'),
      items: lines(inv['items'], 'description', 'subtitle'),
    );
  }
}

class OnuStatus {
  const OnuStatus({
    required this.linked,
    required this.label,
    required this.serial,
    required this.message,
    required this.rxDbm,
    required this.txDbm,
    required this.status,
  });

  final bool linked;
  final String label;
  final String serial;
  final String message;
  final String rxDbm;
  final String txDbm;
  final String status;

  factory OnuStatus.fromJson(Map<String, dynamic> j) => OnuStatus(
        linked: j['linked'] == true,
        label: _s(j['label'], 'ONU'),
        serial: _s(j['serial']),
        message: _s(j['message']),
        rxDbm: _s(j['rx_dbm'], '—'),
        txDbm: _s(j['tx_dbm'], '—'),
        status: _s(j['status'], '—'),
      );

  static const empty =
      OnuStatus(linked: false, label: 'ONU', serial: '', message: '', rxDbm: '—', txDbm: '—', status: '—');
}
