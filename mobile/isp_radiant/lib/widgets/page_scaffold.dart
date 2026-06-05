import 'package:flutter/material.dart';
import 'package:flutter/services.dart';

import '../core/theme/design_tokens.dart';

/// Pushed staff screens — ISP blue app bar + light body.
class PageScaffold extends StatelessWidget {
  const PageScaffold({
    super.key,
    required this.title,
    required this.body,
    this.actions,
    this.floatingActionButton,
    this.bottom,
    this.useGradientBody = false,
  });

  final String title;
  final Widget body;
  final List<Widget>? actions;
  final Widget? floatingActionButton;
  final PreferredSizeWidget? bottom;
  final bool useGradientBody;

  @override
  Widget build(BuildContext context) {
    return AnnotatedRegion<SystemUiOverlayStyle>(
      value: SystemUiOverlayStyle.light.copyWith(statusBarColor: Colors.transparent),
      child: Scaffold(
        backgroundColor: DesignTokens.lightBg,
        appBar: AppBar(
          systemOverlayStyle: SystemUiOverlayStyle.light,
          backgroundColor: DesignTokens.primary,
          foregroundColor: Colors.white,
          elevation: 0,
          centerTitle: true,
          title: Text(
            title,
            style: const TextStyle(fontWeight: FontWeight.w700, fontSize: 17),
            maxLines: 1,
            overflow: TextOverflow.ellipsis,
          ),
          actions: actions,
          bottom: bottom,
        ),
        floatingActionButton: floatingActionButton,
        body: useGradientBody
            ? Column(
                crossAxisAlignment: CrossAxisAlignment.stretch,
                children: [
                  Container(
                    height: 3,
                    decoration: BoxDecoration(
                      gradient: LinearGradient(colors: context.brand.heroGradient),
                    ),
                  ),
                  Expanded(child: body),
                ],
              )
            : body,
      ),
    );
  }
}
