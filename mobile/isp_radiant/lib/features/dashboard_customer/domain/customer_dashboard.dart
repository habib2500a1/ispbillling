/// Typed view of the `/customer/dashboard` + `/customer/usage/live` payloads.
/// All fields are null-safe with sane defaults so the UI can never crash on a
/// missing/changed key (backend stays unchanged; we only re-shape its JSON).
library;

double _toDouble(dynamic v) =>
    v is num ? v.toDouble() : double.tryParse('${v ?? ''}') ?? 0;

String _str(dynamic v, [String fallback = '—']) {
  final s = v?.toString();
  return (s == null || s.isEmpty) ? fallback : s;
}

class CustomerDashboard {
  const CustomerDashboard({
    required this.name,
    required this.code,
    required this.status,
    required this.connected,
    required this.monthlyBill,
    required this.paid,
    required this.totalDue,
    required this.packageName,
    required this.expireDate,
    required this.notices,
    required this.traffic,
  });

  final String name;
  final String code;
  final String status;
  final bool connected;
  final double monthlyBill;
  final double paid;
  final double totalDue;
  final String packageName;
  final String expireDate;
  final List<DashboardNotice> notices;
  final TrafficSnapshot traffic;

  factory CustomerDashboard.fromJson(Map<String, dynamic> json) {
    final customer = json['customer'] as Map<String, dynamic>? ?? const {};
    final summary = json['summary'] as Map<String, dynamic>? ?? const {};
    final status = _str(summary['status'], '—');
    return CustomerDashboard(
      name: _str(customer['name'], 'Client'),
      code: _str(customer['customer_code'], ''),
      status: status,
      connected: status.toLowerCase() == 'connected',
      monthlyBill: _toDouble(summary['monthly_bill']),
      paid: _toDouble(summary['paid']),
      totalDue: _toDouble(json['total_due']),
      packageName: _str(summary['package_name']),
      expireDate: _str(summary['expire_date']),
      notices: (json['notices'] as List<dynamic>? ?? const [])
          .whereType<Map>()
          .map((e) => DashboardNotice.fromJson(Map<String, dynamic>.from(e)))
          .toList(),
      traffic: TrafficSnapshot.fromJson(json['traffic'] as Map<String, dynamic>? ?? const {}),
    );
  }

  CustomerDashboard withTraffic(TrafficSnapshot t) => CustomerDashboard(
        name: name,
        code: code,
        status: status,
        connected: connected,
        monthlyBill: monthlyBill,
        paid: paid,
        totalDue: totalDue,
        packageName: packageName,
        expireDate: expireDate,
        notices: notices,
        traffic: t,
      );
}

class DashboardNotice {
  const DashboardNotice({required this.title, required this.body});
  final String title;
  final String body;

  factory DashboardNotice.fromJson(Map<String, dynamic> json) =>
      DashboardNotice(title: _str(json['title'], ''), body: _str(json['body'], ''));
}

class TrafficSnapshot {
  const TrafficSnapshot({
    required this.downloadHuman,
    required this.uploadHuman,
    required this.monthDownload,
    required this.monthUpload,
    required this.uptime,
    required this.chart,
  });

  final String downloadHuman;
  final String uploadHuman;
  final num monthDownload;
  final num monthUpload;
  final String uptime;
  final Map<String, dynamic>? chart;

  factory TrafficSnapshot.fromJson(Map<String, dynamic> json) => TrafficSnapshot(
        downloadHuman: _str(json['download_human']),
        uploadHuman: _str(json['upload_human']),
        monthDownload: (json['month_download'] ?? json['today_download'] ?? 0) as num,
        monthUpload: (json['month_upload'] ?? json['today_upload'] ?? 0) as num,
        uptime: _str(json['uptime'] ?? json['connection_duration']),
        chart: json['chart'] as Map<String, dynamic>?,
      );

  /// Build from the `/customer/usage/live` `usage` object.
  factory TrafficSnapshot.fromLive(Map<String, dynamic> live) => TrafficSnapshot(
        downloadHuman: _str(live['download_human']),
        uploadHuman: _str(live['upload_human']),
        monthDownload: (live['total_download'] ?? live['today_download'] ?? 0) as num,
        monthUpload: (live['total_upload'] ?? live['today_upload'] ?? 0) as num,
        uptime: _str(live['connection_duration']),
        chart: live['chart'] as Map<String, dynamic>?,
      );

  static const empty = TrafficSnapshot(
    downloadHuman: '—',
    uploadHuman: '—',
    monthDownload: 0,
    monthUpload: 0,
    uptime: '—',
    chart: null,
  );
}
