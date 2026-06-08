import 'staff_interface.dart';

/// Derives available staff interfaces from `/me` roles (Spatie) — no backend change required.
class RoleCapabilities {
  const RoleCapabilities({
    required this.staffInterfaces,
    required this.defaultStaffInterface,
    required this.spatieRoles,
  });

  final List<String> staffInterfaces;
  final String defaultStaffInterface;
  final List<String> spatieRoles;

  bool get hasMultipleInterfaces => staffInterfaces.length > 1;

  bool canUse(String interfaceId) => staffInterfaces.contains(interfaceId);

  static RoleCapabilities fromMe(Map<String, dynamic> me, {String? savedMode}) {
    final roles = _normalizeRoles(me['roles']);
    final available = <String>{};

    if (_hasAny(roles, const [
      'super-admin',
      'isp-admin',
      'admin',
      'isp-manager',
      'isp-billing',
      'branch-manager',
    ])) {
      available.add(StaffInterface.admin);
    }

    if (_hasAny(roles, const [
      'super-admin',
      'isp-admin',
      'admin',
      'cashier',
      'branch-manager',
    ])) {
      available.add(StaffInterface.collector);
    }

    if (_hasAny(roles, const [
      'super-admin',
      'isp-admin',
      'isp-noc',
      'noc',
      'isp-manager',
    ])) {
      available.add(StaffInterface.noc);
    }

    if (_hasAny(roles, const [
      'super-admin',
      'isp-admin',
      'isp-engineer',
      'isp-support',
      'isp-manager',
    ])) {
      available.add(StaffInterface.technician);
    }

    if (available.isEmpty) {
      available.add(StaffInterface.admin);
    }

    final ordered = [
      StaffInterface.admin,
      StaffInterface.collector,
      StaffInterface.noc,
      StaffInterface.technician,
    ].where(available.contains).toList();

    final fallback = _inferDefault(roles, ordered);
    final saved = savedMode?.trim();
    final defaultMode = saved != null && saved.isNotEmpty && ordered.contains(saved) ? saved : fallback;

    return RoleCapabilities(
      staffInterfaces: ordered,
      defaultStaffInterface: defaultMode,
      spatieRoles: roles,
    );
  }

  static List<String> _normalizeRoles(dynamic raw) {
    if (raw is! List) return const [];
    return raw.map((e) => e.toString().toLowerCase().trim()).where((e) => e.isNotEmpty).toList();
  }

  static bool _hasAny(List<String> roles, List<String> needles) =>
      roles.any((r) => needles.contains(r));

  static String _inferDefault(List<String> roles, List<String> ordered) {
    if (_hasAny(roles, const ['isp-engineer']) && ordered.contains(StaffInterface.technician)) {
      return StaffInterface.technician;
    }
    if (_hasAny(roles, const ['cashier']) && ordered.contains(StaffInterface.collector)) {
      return StaffInterface.collector;
    }
    if (_hasAny(roles, const ['isp-noc', 'noc']) && ordered.contains(StaffInterface.noc)) {
      return StaffInterface.noc;
    }
    return ordered.first;
  }
}
