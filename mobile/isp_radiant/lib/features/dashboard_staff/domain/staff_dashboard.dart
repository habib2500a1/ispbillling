/// Typed view of `/staff/dashboard`. Config-driven UI lists (modules,
/// quick_actions, charts) are passed through as raw maps since they are
/// rendered by existing widgets; the numeric KPIs/billing/counters are typed
/// and null-safe so the dashboard can never crash on a missing key.
library;

double _d(dynamic v) => v is num ? v.toDouble() : double.tryParse('${v ?? ''}') ?? 0;
int _i(dynamic v) => v is num ? v.toInt() : int.tryParse('${v ?? ''}') ?? 0;

class StaffDashboard {
  const StaffDashboard({
    required this.user,
    required this.kpis,
    required this.billing,
    required this.finance,
    required this.tickets,
    required this.tasks,
    required this.revenue7d,
    required this.zoneChart,
    required this.modules,
    required this.quickActions,
  });

  final Map<String, dynamic>? user;
  final StaffKpis kpis;
  final StaffBilling billing;
  final FinanceSummary finance;
  final CountStat tickets;
  final CountStat tasks;
  final RevenueSeries revenue7d;
  final List<ZoneRow> zoneChart;
  final List<Map<String, dynamic>> modules;
  final List<Map<String, dynamic>> quickActions;

  factory StaffDashboard.fromJson(Map<String, dynamic> json) {
    List<Map<String, dynamic>> maps(dynamic raw) => (raw as List<dynamic>? ?? const [])
        .whereType<Map>()
        .map((e) => Map<String, dynamic>.from(e))
        .toList();

    return StaffDashboard(
      user: json['user'] as Map<String, dynamic>?,
      kpis: StaffKpis.fromJson(json['kpis'] as Map<String, dynamic>? ?? const {}),
      billing: StaffBilling.fromJson(json['billing'] as Map<String, dynamic>? ?? const {}),
      finance: FinanceSummary.fromJson(json['finance'] as Map<String, dynamic>? ?? const {}),
      tickets: CountStat.fromJson(json['tickets'] as Map<String, dynamic>? ?? const {}),
      tasks: CountStat.fromJson(json['tasks'] as Map<String, dynamic>? ?? const {}),
      revenue7d: RevenueSeries.fromJson(json['revenue_chart_7d'] as Map<String, dynamic>? ?? const {}),
      zoneChart: maps(json['zone_collection_chart']).map(ZoneRow.fromJson).toList(),
      modules: maps(json['app_modules']),
      quickActions: maps(json['quick_actions']),
    );
  }
}

class StaffKpis {
  const StaffKpis({
    required this.collectedToday,
    required this.cashOnHand,
    required this.onlineClients,
    required this.dueClients,
    required this.activeClients,
    required this.expiringToday,
  });

  final double collectedToday;
  final double cashOnHand;
  final int onlineClients;
  final int dueClients;
  final int activeClients;
  final int expiringToday;

  factory StaffKpis.fromJson(Map<String, dynamic> j) => StaffKpis(
        collectedToday: _d(j['collected_today']),
        cashOnHand: _d(j['cash_on_hand'] ?? j['collected_today']),
        onlineClients: _i(j['online_clients']),
        dueClients: _i(j['due_clients']),
        activeClients: _i(j['active_clients']),
        expiringToday: _i(j['expiring_today']),
      );
}

class StaffBilling {
  const StaffBilling({
    required this.monthlyBill,
    required this.collected,
    required this.due,
    required this.discount,
  });

  final double monthlyBill;
  final double collected;
  final double due;
  final double discount;

  factory StaffBilling.fromJson(Map<String, dynamic> j) => StaffBilling(
        monthlyBill: _d(j['monthly_bill']),
        collected: _d(j['collected_bill']),
        due: _d(j['due']),
        discount: _d(j['discount']),
      );
}

class FinanceSummary {
  const FinanceSummary({
    required this.collectedMonth,
    required this.expenseMonth,
    required this.netMonth,
    required this.paidSalaryMonth,
    required this.resellerSettledMonth,
    required this.resellerWallet,
  });

  final double collectedMonth;
  final double expenseMonth;
  final double netMonth;
  final double paidSalaryMonth;
  final double resellerSettledMonth;
  final double resellerWallet;

  /// True when there's any reseller/payroll activity worth showing a card for.
  bool get hasExtended =>
      paidSalaryMonth > 0 || resellerSettledMonth > 0 || resellerWallet > 0;

  factory FinanceSummary.fromJson(Map<String, dynamic> j) => FinanceSummary(
        collectedMonth: _d(j['collected_month']),
        expenseMonth: _d(j['expense_month']),
        netMonth: _d(j['net_month']),
        paidSalaryMonth: _d(j['paid_salary_month']),
        resellerSettledMonth: _d(j['reseller_settled_month']),
        resellerWallet: _d(j['reseller_wallet']),
      );

  static const empty = FinanceSummary(
    collectedMonth: 0,
    expenseMonth: 0,
    netMonth: 0,
    paidSalaryMonth: 0,
    resellerSettledMonth: 0,
    resellerWallet: 0,
  );
}

class CountStat {
  const CountStat({required this.total, required this.pending, required this.process});
  final int total;
  final int pending;
  final int process;

  factory CountStat.fromJson(Map<String, dynamic> j) =>
      CountStat(total: _i(j['total']), pending: _i(j['pending']), process: _i(j['process']));
}

class RevenueSeries {
  const RevenueSeries({required this.labels, required this.collected});
  final List<String> labels;
  final List<double> collected;
  bool get isEmpty => collected.isEmpty;

  factory RevenueSeries.fromJson(Map<String, dynamic> j) => RevenueSeries(
        labels: (j['labels'] as List<dynamic>? ?? const []).map((e) => e.toString()).toList(),
        collected: (j['collected'] as List<dynamic>? ?? const []).map(_d).toList(),
      );
}

class ZoneRow {
  const ZoneRow({required this.zone, required this.paid, required this.unpaid});
  final String zone;
  final double paid;
  final double unpaid;

  factory ZoneRow.fromJson(Map<String, dynamic> j) =>
      ZoneRow(zone: '${j['zone'] ?? ''}', paid: _d(j['paid']), unpaid: _d(j['unpaid']));
}
