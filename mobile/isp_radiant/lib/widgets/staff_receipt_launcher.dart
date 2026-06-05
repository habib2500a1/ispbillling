import 'package:flutter/material.dart';

import '../screens/staff_money_receipt_screen.dart';
import '../services/api_service.dart';

/// Opens money receipt above the staff bottom nav (keeps Home/Billing/Collection visible).
class StaffReceiptRequest {
  const StaffReceiptRequest({
    required this.paymentId,
    this.initialPdfUrl,
    this.seedData,
  });

  final int paymentId;
  final String? initialPdfUrl;
  final Map<String, dynamic>? seedData;
}

class StaffReceiptLauncher extends InheritedWidget {
  const StaffReceiptLauncher({
    super.key,
    required this.api,
    required this.openReceipt,
    required super.child,
  });

  final ApiService api;
  final ValueChanged<StaffReceiptRequest> openReceipt;

  static StaffReceiptLauncher? maybeOf(BuildContext context) {
    return context.dependOnInheritedWidgetOfExactType<StaffReceiptLauncher>();
  }

  static void open(
    BuildContext context, {
    required ApiService api,
    required int paymentId,
    String? initialPdfUrl,
    Map<String, dynamic>? seedData,
  }) {
    final launcher = maybeOf(context);
    final req = StaffReceiptRequest(
      paymentId: paymentId,
      initialPdfUrl: initialPdfUrl,
      seedData: seedData,
    );
    if (launcher != null) {
      launcher.openReceipt(req);
      return;
    }
    Navigator.push(
      context,
      MaterialPageRoute(
        builder: (_) => StaffMoneyReceiptScreen(
          api: api,
          paymentId: paymentId,
          initialPdfUrl: initialPdfUrl,
          seedData: seedData,
        ),
      ),
    );
  }

  @override
  bool updateShouldNotify(StaffReceiptLauncher oldWidget) => false;
}
