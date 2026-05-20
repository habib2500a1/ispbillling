import 'package:flutter/material.dart';
import 'package:flutter/services.dart';

void showSnack(BuildContext context, String msg, {bool isError = false}) {
  HapticFeedback.selectionClick();
  ScaffoldMessenger.of(context).hideCurrentSnackBar();
  ScaffoldMessenger.of(context).showSnackBar(
    SnackBar(
      content: Text(msg),
      behavior: SnackBarBehavior.floating,
      backgroundColor: isError ? Colors.red.shade700 : null,
    ),
  );
}

void onTabTap(int index, ValueChanged<int> setTab) {
  HapticFeedback.lightImpact();
  setTab(index);
}
