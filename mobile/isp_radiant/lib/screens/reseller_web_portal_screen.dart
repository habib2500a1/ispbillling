import 'package:flutter/material.dart';
import 'package:webview_flutter/webview_flutter.dart';

import '../theme/app_theme.dart';

/// Reseller partner portal (web) inside the app — same entry as website /reseller/login.
class ResellerWebPortalScreen extends StatefulWidget {
  const ResellerWebPortalScreen({super.key, required this.initialUrl, required this.title});

  final String initialUrl;
  final String title;

  @override
  State<ResellerWebPortalScreen> createState() => _ResellerWebPortalScreenState();
}

class _ResellerWebPortalScreenState extends State<ResellerWebPortalScreen> {
  late final WebViewController _controller;
  bool _loading = true;
  late final Uri _initialUri;

  @override
  void initState() {
    super.initState();
    _initialUri = Uri.parse(widget.initialUrl);
    _controller = WebViewController()
      ..setJavaScriptMode(JavaScriptMode.unrestricted)
      ..setNavigationDelegate(
        NavigationDelegate(
          onNavigationRequest: (request) {
            if (!_isAllowedUrl(request.url)) {
              return NavigationDecision.prevent;
            }
            return NavigationDecision.navigate;
          },
          onPageFinished: (_) {
            if (mounted) setState(() => _loading = false);
          },
        ),
      )
      ..loadRequest(Uri.parse(widget.initialUrl));
  }

  bool _isAllowedUrl(String url) {
    final uri = Uri.tryParse(url);
    if (uri == null) return false;
    if (uri.scheme != 'https') return false;
    if (uri.host.isEmpty) return false;

    final host = uri.host.toLowerCase();
    final originHost = _initialUri.host.toLowerCase();
    return host == originHost || host.endsWith('.$originHost');
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(
        title: Text(widget.title),
        backgroundColor: AppTheme.primary,
        foregroundColor: Colors.white,
      ),
      body: Stack(
        children: [
          WebViewWidget(controller: _controller),
          if (_loading)
            const Center(child: CircularProgressIndicator()),
        ],
      ),
    );
  }
}
