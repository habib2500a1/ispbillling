import 'package:flutter/material.dart';

import '../core/theme/design_tokens.dart';
import '../services/api_service.dart';
import '../services/staff_pdf_service.dart';
import '../utils/app_nav.dart';
import 'staff_authenticated_pdf_screen.dart';

/// Fixed bottom dock — Print + Share for staff billing documents.
class StaffDocumentActionsBar extends StatefulWidget {
  const StaffDocumentActionsBar({
    super.key,
    required this.api,
    required this.pdfUrl,
    required this.filename,
    this.pdfTitle = 'Document',
  });

  final ApiService api;
  final String pdfUrl;
  final String filename;
  final String pdfTitle;

  @override
  State<StaffDocumentActionsBar> createState() => _StaffDocumentActionsBarState();
}

class _StaffDocumentActionsBarState extends State<StaffDocumentActionsBar> {
  bool _busy = false;
  late final StaffPdfService _pdf = StaffPdfService(widget.api);

  Future<void> _print() async {
    if (widget.pdfUrl.isEmpty) {
      showSnack(context, 'PDF not available', isError: true);
      return;
    }
    await Navigator.push(
      context,
      MaterialPageRoute(
        builder: (_) => StaffAuthenticatedPdfScreen(
          api: widget.api,
          url: widget.pdfUrl,
          title: widget.pdfTitle,
        ),
      ),
    );
  }

  Future<void> _share() async {
    if (widget.pdfUrl.isEmpty) {
      showSnack(context, 'PDF not available', isError: true);
      return;
    }
    setState(() => _busy = true);
    try {
      await _pdf.sharePdf(widget.pdfUrl, filename: widget.filename);
    } on ApiException catch (e) {
      if (mounted) showSnack(context, e.message, isError: true);
    } finally {
      if (mounted) setState(() => _busy = false);
    }
  }

  @override
  Widget build(BuildContext context) {
    return Container(
      decoration: BoxDecoration(
        color: Colors.white,
        border: const Border(top: BorderSide(color: DesignTokens.lightBorder)),
        boxShadow: [
          BoxShadow(
            color: Colors.black.withValues(alpha: 0.08),
            blurRadius: 20,
            offset: const Offset(0, -6),
          ),
        ],
      ),
      child: SafeArea(
        top: false,
        child: Padding(
          padding: const EdgeInsets.fromLTRB(16, 12, 16, 10),
          child: Row(
            children: [
              Expanded(
                child: _DockButton(
                  icon: Icons.print_rounded,
                  label: 'Print',
                  filled: true,
                  busy: false,
                  onPressed: _print,
                ),
              ),
              const SizedBox(width: 12),
              Expanded(
                child: _DockButton(
                  icon: Icons.ios_share_rounded,
                  label: 'Share',
                  filled: false,
                  busy: _busy,
                  onPressed: _share,
                ),
              ),
            ],
          ),
        ),
      ),
    );
  }
}

class _DockButton extends StatelessWidget {
  const _DockButton({
    required this.icon,
    required this.label,
    required this.filled,
    required this.busy,
    required this.onPressed,
  });

  final IconData icon;
  final String label;
  final bool filled;
  final bool busy;
  final VoidCallback onPressed;

  @override
  Widget build(BuildContext context) {
    final child = busy
        ? const SizedBox(
            width: 22,
            height: 22,
            child: CircularProgressIndicator(strokeWidth: 2.5, color: DesignTokens.primary),
          )
        : Row(
            mainAxisAlignment: MainAxisAlignment.center,
            children: [
              Icon(icon, size: 22),
              const SizedBox(width: 8),
              Text(label, style: const TextStyle(fontWeight: FontWeight.w800, fontSize: 15)),
            ],
          );

    if (filled) {
      return Material(
        color: DesignTokens.primary,
        borderRadius: BorderRadius.circular(14),
        elevation: 2,
        shadowColor: DesignTokens.primary.withValues(alpha: 0.35),
        child: InkWell(
          onTap: busy ? null : onPressed,
          borderRadius: BorderRadius.circular(14),
          child: Container(
            height: 52,
            alignment: Alignment.center,
            child: DefaultTextStyle(
              style: const TextStyle(color: Colors.white),
              child: child,
            ),
          ),
        ),
      );
    }

    return Material(
      color: DesignTokens.lightSurfaceAlt,
      borderRadius: BorderRadius.circular(14),
      child: InkWell(
        onTap: busy ? null : onPressed,
        borderRadius: BorderRadius.circular(14),
        child: Container(
          height: 52,
          alignment: Alignment.center,
          decoration: BoxDecoration(
            borderRadius: BorderRadius.circular(14),
            border: Border.all(color: DesignTokens.primary.withValues(alpha: 0.35)),
          ),
          child: DefaultTextStyle(
            style: const TextStyle(color: DesignTokens.primary),
            child: child,
          ),
        ),
      ),
    );
  }
}
