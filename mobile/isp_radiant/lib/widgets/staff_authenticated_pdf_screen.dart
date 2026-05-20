import 'package:flutter/material.dart';
import 'package:webview_flutter/webview_flutter.dart';

import '../services/api_service.dart';

/// Opens staff invoice/receipt PDF with Bearer token (Sanctum).
class StaffAuthenticatedPdfScreen extends StatefulWidget {
  const StaffAuthenticatedPdfScreen({
    super.key,
    required this.api,
    required this.url,
    required this.title,
  });

  final ApiService api;
  final String url;
  final String title;

  @override
  State<StaffAuthenticatedPdfScreen> createState() => _StaffAuthenticatedPdfScreenState();
}

class _StaffAuthenticatedPdfScreenState extends State<StaffAuthenticatedPdfScreen> {
  late final WebViewController _controller;
  bool _loading = true;
  String? _error;

  @override
  void initState() {
    super.initState();
    _controller = WebViewController()
      ..setJavaScriptMode(JavaScriptMode.unrestricted)
      ..setNavigationDelegate(
        NavigationDelegate(
          onPageStarted: (_) => setState(() => _loading = true),
          onPageFinished: (_) => setState(() => _loading = false),
          onWebResourceError: (e) => setState(() {
            _loading = false;
            _error = e.description;
          }),
        ),
      );
    _load();
  }

  Future<void> _load() async {
    final token = await widget.api.token;
    if (token == null || token.isEmpty) {
      setState(() {
        _loading = false;
        _error = 'Not logged in';
      });
      return;
    }
    await _controller.loadRequest(
      Uri.parse(widget.url),
      headers: {
        'Authorization': 'Bearer $token',
        'Accept': 'application/pdf',
      },
    );
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(title: Text(widget.title)),
      body: Stack(
        children: [
          if (_error != null)
            Center(child: Padding(padding: const EdgeInsets.all(24), child: Text(_error!)))
          else
            WebViewWidget(controller: _controller),
          if (_loading) const LinearProgressIndicator(minHeight: 3),
        ],
      ),
    );
  }
}
