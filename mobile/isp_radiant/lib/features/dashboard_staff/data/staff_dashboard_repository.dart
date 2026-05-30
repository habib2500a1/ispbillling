import '../../../core/network/api_result.dart';
import '../../../services/api_service.dart';
import '../domain/staff_dashboard.dart';

/// Repository for the staff dashboard — wraps [ApiService.staffDashboard] and
/// returns a typed [StaffDashboard]. Kept as a plain class (not a Riverpod
/// provider) because [StaffHomeScreen] owns a heavy imperative lifecycle
/// (realtime ticks, offline sync, SMS listener) and drives loads itself.
class StaffDashboardRepository {
  StaffDashboardRepository(this._api);
  final ApiService _api;

  Future<Result<StaffDashboard>> load() =>
      guard(() async => StaffDashboard.fromJson(await _api.staffDashboard()));
}
