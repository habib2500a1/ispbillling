import 'package:flutter/material.dart';

/// Space reserved above bottom navigation + optional FAB.
const double kShellBottomInset = 88;

EdgeInsets pagePadding(BuildContext context, {double top = 12}) {
  return EdgeInsets.fromLTRB(14, top, 14, kShellBottomInset);
}
