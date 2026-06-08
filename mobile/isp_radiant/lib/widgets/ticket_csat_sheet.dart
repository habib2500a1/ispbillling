import 'package:flutter/material.dart';
import 'package:shared_preferences/shared_preferences.dart';

/// Post-ticket CSAT feedback (Phase 4) — stored locally until backend endpoint exists.
class TicketCsatSheet {
  static Future<void> maybeShow(BuildContext context, {required int ticketId, required String ticketSubject}) async {
    final prefs = await SharedPreferences.getInstance();
    final key = 'csat_ticket_$ticketId';
    if (prefs.containsKey(key)) return;

    if (!context.mounted) return;
    final rating = await showModalBottomSheet<int>(
      context: context,
      showDragHandle: true,
      builder: (ctx) {
        var selected = 0;
        return StatefulBuilder(
          builder: (ctx, setState) {
            return Padding(
              padding: const EdgeInsets.fromLTRB(20, 0, 20, 24),
              child: Column(
                mainAxisSize: MainAxisSize.min,
                crossAxisAlignment: CrossAxisAlignment.stretch,
                children: [
                  const Text('How was your support?', style: TextStyle(fontSize: 18, fontWeight: FontWeight.w800)),
                  Text(ticketSubject, style: TextStyle(fontSize: 13, color: Colors.grey.shade600)),
                  const SizedBox(height: 16),
                  Row(
                    mainAxisAlignment: MainAxisAlignment.center,
                    children: List.generate(5, (i) {
                      final star = i + 1;
                      return IconButton(
                        icon: Icon(star <= selected ? Icons.star : Icons.star_border, color: Colors.amber, size: 36),
                        onPressed: () => setState(() => selected = star),
                      );
                    }),
                  ),
                  FilledButton(
                    onPressed: selected > 0 ? () => Navigator.pop(ctx, selected) : null,
                    child: const Text('Submit feedback'),
                  ),
                  TextButton(onPressed: () => Navigator.pop(ctx), child: const Text('Skip')),
                ],
              ),
            );
          },
        );
      },
    );

    if (rating != null && rating > 0) {
      await prefs.setInt(key, rating);
      if (context.mounted) {
        ScaffoldMessenger.of(context).showSnackBar(const SnackBar(content: Text('Thanks for your feedback!')));
      }
    }
  }
}
