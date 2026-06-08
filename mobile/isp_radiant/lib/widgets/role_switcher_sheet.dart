import 'package:flutter/material.dart';

import '../core/navigation/super_app_navigator.dart';
import '../core/roles/role_resolver.dart';
import '../core/roles/staff_interface.dart';
import '../core/theme/design_tokens.dart';
import '../services/api_service.dart';

/// Bottom sheet for staff users with multiple interfaces (admin / collector / NOC / technician).
Future<void> showRoleSwitcherSheet(
  BuildContext context, {
  required ApiService api,
  required RoleCapabilities capabilities,
  required String currentMode,
  Map<String, dynamic> loginPayload = const {},
}) {
  return showModalBottomSheet<void>(
    context: context,
    showDragHandle: true,
    builder: (ctx) {
      return SafeArea(
        child: Padding(
          padding: const EdgeInsets.fromLTRB(16, 0, 16, 16),
          child: Column(
            mainAxisSize: MainAxisSize.min,
            crossAxisAlignment: CrossAxisAlignment.stretch,
            children: [
              const Text('Switch workspace', style: TextStyle(fontSize: 18, fontWeight: FontWeight.w800)),
              const SizedBox(height: 4),
              Text(
                'Your account has ${capabilities.staffInterfaces.length} roles in this app.',
                style: TextStyle(fontSize: 13, color: Colors.grey.shade600),
              ),
              const SizedBox(height: 12),
              ...capabilities.staffInterfaces.map((mode) {
                final selected = mode == currentMode;
                return ListTile(
                  shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(DesignTokens.radiusSm)),
                  tileColor: selected ? DesignTokens.primary.withValues(alpha: 0.08) : null,
                  leading: Icon(_iconFor(mode), color: selected ? DesignTokens.primary : null),
                  title: Text(StaffInterface.labelFor(mode), style: TextStyle(fontWeight: selected ? FontWeight.w700 : FontWeight.w500)),
                  trailing: selected ? const Icon(Icons.check_circle_rounded, color: DesignTokens.primary) : null,
                  onTap: selected
                      ? null
                      : () async {
                          Navigator.pop(ctx);
                          await SuperAppNavigator.switchStaffInterface(
                            context,
                            api,
                            newMode: mode,
                            loginPayload: loginPayload,
                          );
                        },
                );
              }),
            ],
          ),
        ),
      );
    },
  );
}

IconData _iconFor(String mode) {
  switch (mode) {
    case StaffInterface.collector:
      return Icons.account_balance_wallet_outlined;
    case StaffInterface.noc:
      return Icons.cell_tower_rounded;
    case StaffInterface.technician:
      return Icons.engineering_outlined;
    default:
      return Icons.dashboard_outlined;
  }
}
