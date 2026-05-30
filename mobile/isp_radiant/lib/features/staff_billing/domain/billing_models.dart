/// Typed, null-safe models for the staff billing area (summary, due clients,
/// invoices, collections). Backend JSON unchanged.
library;

double _d(dynamic v) => v is num ? v.toDouble() : double.tryParse('${v ?? ''}') ?? 0;
int _i(dynamic v) => v is num ? v.toInt() : int.tryParse('${v ?? ''}') ?? 0;
String _s(dynamic v, [String f = '']) {
  final s = v?.toString();
  return (s == null || s.isEmpty) ? f : s;
}

class BillingSummary {
  const BillingSummary({
    required this.monthlyBill,
    required this.collected,
    required this.due,
    required this.discount,
    required this.paidClients,
    required this.unpaidClients,
  });

  final double monthlyBill;
  final double collected;
  final double due;
  final double discount;
  final int paidClients;
  final int unpaidClients;

  factory BillingSummary.fromJson(Map<String, dynamic> j) => BillingSummary(
        monthlyBill: _d(j['monthly_bill']),
        collected: _d(j['collected_bill']),
        due: _d(j['due']),
        discount: _d(j['discount']),
        paidClients: _i(j['paid_clients']),
        unpaidClients: _i(j['unpaid_clients']),
      );

  static const empty =
      BillingSummary(monthlyBill: 0, collected: 0, due: 0, discount: 0, paidClients: 0, unpaidClients: 0);
}

class DueClient {
  const DueClient({
    required this.id,
    required this.name,
    required this.customerCode,
    required this.username,
    required this.phone,
    required this.zone,
    required this.address,
    required this.package,
    required this.balanceDue,
    required this.monthlyBill,
    required this.expireDay,
    required this.networkOn,
  });

  final int id;
  final String name;
  final String customerCode;
  final String username;
  final String phone;
  final String zone;
  final String address;
  final String package;
  final double balanceDue;
  final double monthlyBill;
  final String expireDay;
  final bool networkOn;

  factory DueClient.fromJson(Map<String, dynamic> j) => DueClient(
        id: _i(j['id']),
        name: _s(j['name'], 'Client'),
        customerCode: _s(j['customer_code']),
        username: _s(j['username'], _s(j['phone'])),
        phone: _s(j['phone']),
        zone: _s(j['zone']),
        address: _s(j['address']),
        package: _s(j['package']),
        balanceDue: _d(j['balance_due']),
        monthlyBill: _d(j['monthly_bill'] ?? j['monthly_payable']),
        expireDay: _s(j['expire_day'], '—'),
        networkOn: j['network_on'] != false,
      );
}

class InvoiceRow {
  const InvoiceRow({
    required this.id,
    required this.invoiceNumber,
    required this.customerId,
    required this.customerName,
    required this.dueDate,
    required this.status,
    required this.balanceDue,
  });

  final int id;
  final String invoiceNumber;
  final int? customerId;
  final String customerName;
  final String dueDate;
  final String status;
  final double balanceDue;

  factory InvoiceRow.fromJson(Map<String, dynamic> j) => InvoiceRow(
        id: _i(j['id']),
        invoiceNumber: _s(j['invoice_number']),
        customerId: j['customer_id'] is num ? (j['customer_id'] as num).toInt() : null,
        customerName: _s(j['customer_name']),
        dueDate: _s(j['due_date'], '—'),
        status: _s(j['status']),
        balanceDue: _d(j['balance_due']),
      );
}

class CollectionRecord {
  const CollectionRecord({
    required this.name,
    required this.customerCode,
    required this.address,
    required this.amount,
    required this.discount,
    required this.due,
    required this.method,
    required this.receivedBy,
    required this.createdAt,
    required this.phone,
  });

  final String name;
  final String customerCode;
  final String address;
  final double amount;
  final double discount;
  final double due;
  final String method;
  final String receivedBy;
  final String createdAt;
  final String phone;

  factory CollectionRecord.fromJson(Map<String, dynamic> j) => CollectionRecord(
        name: _s(j['customer_name'], 'Client'),
        customerCode: _s(j['customer_code']),
        address: _s(j['address']),
        amount: _d(j['amount']),
        discount: _d(j['discount']),
        due: _d(j['due'] ?? j['balance_due']),
        method: _s(j['method']),
        receivedBy: _s(j['recorded_by'], '—'),
        createdAt: _s(j['paid_at'] ?? j['bill_date'], '—'),
        phone: _s(j['phone']),
      );
}

class CollectionSummary {
  const CollectionSummary({required this.transactionCount, required this.collected});
  final int transactionCount;
  final double collected;

  factory CollectionSummary.fromJson(Map<String, dynamic> j, int fallbackCount) => CollectionSummary(
        transactionCount: _i(j['transaction_count'] == 0 ? fallbackCount : (j['transaction_count'] ?? fallbackCount)),
        collected: _d(j['period_collected'] ?? j['month_collected']),
      );

  static const empty = CollectionSummary(transactionCount: 0, collected: 0);
}
