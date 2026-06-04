import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../../../core/network/api_result.dart';
import '../../../core/network/providers.dart';
import '../../../services/api_service.dart';
import '../domain/customer_dashboard.dart';

/// Repository for the customer dashboard. Wraps the proven [ApiService]
/// endpoints and returns typed models / [Result] — screens never touch raw
/// maps or `http` directly.
class CustomerDashboardRepository {
  CustomerDashboardRepository(this._api);
  final ApiService _api;

  Future<Result<CustomerDashboard>> load() => guard(() async {
        final data = await _api.customerDashboard();
        final dash = CustomerDashboard.fromJson(data);
        // Best-effort live traffic enrichment; never fail the whole load on it.
        try {
          final live = await _api.customerUsageLive();
          final usage = live['usage'] as Map<String, dynamic>?;
          if (usage != null) return dash.withTraffic(TrafficSnapshot.fromLive(usage));
        } catch (_) {}
        return dash;
      });

  /// Live polling slice (2s) — returns just the traffic snapshot.
  Future<TrafficSnapshot?> liveTraffic() async {
    try {
      final live = await _api.customerUsageLive();
      final usage = live['usage'] as Map<String, dynamic>?;
      return usage == null ? null : TrafficSnapshot.fromLive(usage);
    } catch (_) {
      return null;
    }
  }
}

final customerDashboardRepositoryProvider = Provider<CustomerDashboardRepository>(
  (ref) => CustomerDashboardRepository(ref.watch(apiServiceProvider)),
);

/// Async dashboard state for the customer home tab.
class CustomerDashboardNotifier extends AsyncNotifier<CustomerDashboard> {
  @override
  Future<CustomerDashboard> build() async {
    final res = await ref.watch(customerDashboardRepositoryProvider).load();
    return res.when(ok: (d) => d, err: (f) => throw f);
  }

  Future<void> refresh() async {
    state = const AsyncLoading<CustomerDashboard>().copyWithPrevious(state);
    final res = await ref.read(customerDashboardRepositoryProvider).load();
    state = res.when(
      ok: (d) => AsyncData(d),
      err: (f) => AsyncError(f, StackTrace.current),
    );
  }

  /// Push a fresh traffic snapshot from the 2s poll without a full reload.
  void applyTraffic(TrafficSnapshot t) {
    final current = state.valueOrNull;
    if (current != null) state = AsyncData(current.withTraffic(t));
  }
}

final customerDashboardProvider =
    AsyncNotifierProvider<CustomerDashboardNotifier, CustomerDashboard>(
  CustomerDashboardNotifier.new,
);
