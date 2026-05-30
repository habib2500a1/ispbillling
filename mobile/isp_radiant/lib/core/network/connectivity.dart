import 'package:connectivity_plus/connectivity_plus.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';

/// Streams the device's online/offline state. Used to show an offline banner
/// and to let screens avoid firing requests that will obviously fail.
final connectivityProvider = StreamProvider<bool>((ref) async* {
  final conn = Connectivity();
  bool isOnline(List<ConnectivityResult> r) =>
      r.any((e) => e != ConnectivityResult.none);

  yield isOnline(await conn.checkConnectivity());
  yield* conn.onConnectivityChanged.map(isOnline);
});

/// Synchronous best-effort read (defaults to online while resolving).
final isOnlineProvider = Provider<bool>((ref) {
  return ref.watch(connectivityProvider).maybeWhen(data: (v) => v, orElse: () => true);
});
