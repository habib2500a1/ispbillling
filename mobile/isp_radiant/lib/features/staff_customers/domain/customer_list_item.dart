/// Typed row for the staff Client List / search results. Null-safe over the
/// existing `/staff/customers` JSON.
library;

double _d(dynamic v) => v is num ? v.toDouble() : double.tryParse('${v ?? ''}') ?? 0;
int _i(dynamic v) => v is num ? v.toInt() : int.tryParse('${v ?? ''}') ?? 0;
String _s(dynamic v, [String f = '']) {
  final s = v?.toString();
  return (s == null || s.isEmpty) ? f : s;
}

class CustomerListItem {
  const CustomerListItem({
    required this.id,
    required this.name,
    required this.customerCode,
    required this.username,
    required this.packageName,
    this.packageSpeed,
    required this.zone,
    required this.phone,
    required this.monthlyBill,
    required this.due,
    required this.isOnline,
    required this.networkOn,
    required this.status,
  });

  final int id;
  final String name;
  final String customerCode;
  final String username;
  final String packageName;
  final double? packageSpeed;
  final String zone;
  final String phone;
  final double monthlyBill;
  final double due;
  final bool isOnline;
  final bool networkOn;
  final String status;

  bool get isActive => status.toLowerCase() == 'active';

  String get packageLabel {
    if (packageSpeed != null && packageSpeed! > 0) {
      final v = packageSpeed!;
      return v == v.roundToDouble() ? '${v.toInt()}Mbps' : '${v}Mbps';
    }
    return packageName;
  }

  String get statusLabel {
    final s = status.toLowerCase();
    if (s.isEmpty) return 'Active';
    return s[0].toUpperCase() + s.substring(1);
  }

  factory CustomerListItem.fromJson(Map<String, dynamic> j) => CustomerListItem(
        id: _i(j['id']),
        name: _s(j['name'], 'Client'),
        customerCode: _s(j['customer_code']),
        username: _s(j['username'], _s(j['customer_code'])),
        packageName: _s(j['package'], _s(j['package_name'])),
        packageSpeed: j['package_speed'] is num ? (j['package_speed'] as num).toDouble() : double.tryParse('${j['package_speed'] ?? ''}'),
        zone: _s(j['zone']),
        phone: _s(j['phone']),
        monthlyBill: _d(j['monthly_bill']),
        due: _d(j['due'] ?? j['balance_due']),
        isOnline: j['is_online'] == true,
        networkOn: j['network_on'] != false,
        status: _s(j['status'], 'active'),
      );
}
