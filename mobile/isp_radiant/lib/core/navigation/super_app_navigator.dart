import 'package:flutter/material.dart';

import '../../screens/customer_home_screen.dart';
import '../../screens/reseller_home_screen.dart';
import '../../screens/staff_home_screen.dart';
import '../../screens/technician_home_screen.dart';
import '../../services/api_service.dart';
import '../roles/role_resolver.dart';
import '../roles/staff_interface.dart';

/// Routes to the correct home shell after login or cold start.
abstract final class SuperAppNavigator {
  static Future<void> goStaffHome(
    BuildContext context,
    ApiService api, {
    required Map<String, dynamic> loginPayload,
    String? staffMode,
  }) async {
    final mode = staffMode ?? await api.staffMode ?? StaffInterface.admin;
    if (StaffInterface.isTechnicianShell(mode)) {
      if (!context.mounted) return;
      Navigator.of(context).pushReplacement(
        MaterialPageRoute(
          builder: (_) => TechnicianHomeScreen(api: api, loginPayload: loginPayload, staffMode: mode),
        ),
      );
      return;
    }

    if (!context.mounted) return;
    Navigator.of(context).pushReplacement(
      MaterialPageRoute(
        builder: (_) => StaffHomeScreen(api: api, loginPayload: loginPayload, staffMode: mode),
      ),
    );
  }

  static Future<void> goCustomerHome(
    BuildContext context,
    ApiService api, {
    required Map<String, dynamic> loginPayload,
  }) async {
    if (!context.mounted) return;
    Navigator.of(context).pushReplacement(
      MaterialPageRoute(
        builder: (_) => CustomerHomeScreen(api: api, loginPayload: loginPayload),
      ),
    );
  }

  static Future<void> goResellerHome(
    BuildContext context,
    ApiService api, {
    required Map<String, dynamic> loginPayload,
  }) async {
    if (!context.mounted) return;
    Navigator.of(context).pushReplacement(
      MaterialPageRoute(
        builder: (_) => ResellerHomeScreen(api: api, loginPayload: loginPayload),
      ),
    );
  }

  static Future<void> applyStaffLogin(
    BuildContext context,
    ApiService api, {
    required Map<String, dynamic> loginBody,
  }) async {
    Map<String, dynamic> me;
    try {
      me = await api.staffMe();
    } catch (_) {
      me = const {};
    }

    final saved = await api.staffMode;
    final caps = RoleCapabilities.fromMe(me, savedMode: saved);
    final mode = caps.defaultStaffInterface;
    await api.saveStaffMode(mode);

    if (!context.mounted) return;
    await goStaffHome(context, api, loginPayload: loginBody, staffMode: mode);
  }

  static Future<void> switchStaffInterface(
    BuildContext context,
    ApiService api, {
    required String newMode,
    Map<String, dynamic> loginPayload = const {},
  }) async {
    await api.saveStaffMode(newMode);
    if (!context.mounted) return;

    if (StaffInterface.isTechnicianShell(newMode)) {
      Navigator.of(context).pushReplacement(
        MaterialPageRoute(
          builder: (_) => TechnicianHomeScreen(api: api, loginPayload: loginPayload, staffMode: newMode),
        ),
      );
      return;
    }

    Navigator.of(context).pushReplacement(
      MaterialPageRoute(
        builder: (_) => StaffHomeScreen(api: api, loginPayload: loginPayload, staffMode: newMode),
      ),
    );
  }
}
