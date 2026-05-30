import 'dart:async';

import 'package:flutter/widgets.dart';
import 'package:flutter_background_service/flutter_background_service.dart';
import 'package:flutter_local_notifications/flutter_local_notifications.dart';
import 'package:shared_preferences/shared_preferences.dart';
import 'package:telephony/telephony.dart';

import 'sms_listener.dart';

/// Persistent foreground service that keeps the process alive so payment SMS are
/// read even when the phone is locked, the app is swiped away, or the OS is in
/// Doze. Two layers of reliability run inside it:
///   1. real-time `telephony` listener (instant catch while the isolate lives)
///   2. a fixed-cadence inbox poll (guaranteed catch if a broadcast is dropped)
const String kSmsServiceChannelId = 'rcl_sms_monitor';
const int kSmsServiceNotificationId = 8801;

const String _prefAuto = 'mfs_auto_enabled';

/// Safety-net cadence. Lower = faster catch but more battery; higher = lazier.
const Duration _pollInterval = Duration(seconds: 20);

/// Configure (but not necessarily start) the foreground service. Called once
/// from `main()`. With `autoStart: true` the service also relaunches on boot;
/// [onServiceStart] re-checks the toggle and stops itself if auto is OFF.
Future<void> initializeBackgroundService() async {
  final service = FlutterBackgroundService();

  // Android 8+ requires the channel to exist before the foreground notification
  // can be shown. Importance.low keeps it silent (no sound/heads-up).
  final notifications = FlutterLocalNotificationsPlugin();
  const channel = AndroidNotificationChannel(
    kSmsServiceChannelId,
    'RCL SMS monitoring',
    description: 'Keeps bKash/Nagad payment SMS auto-forward running in the background',
    importance: Importance.low,
  );
  await notifications
      .resolvePlatformSpecificImplementation<AndroidFlutterLocalNotificationsPlugin>()
      ?.createNotificationChannel(channel);

  await service.configure(
    androidConfiguration: AndroidConfiguration(
      onStart: onServiceStart,
      autoStart: true,
      isForegroundMode: true,
      autoStartOnBoot: true,
      notificationChannelId: kSmsServiceChannelId,
      initialNotificationTitle: 'RCL SMS',
      initialNotificationContent: 'পেমেন্ট SMS মনিটরিং চালু',
      foregroundServiceNotificationId: kSmsServiceNotificationId,
      foregroundServiceTypes: const [AndroidForegroundType.dataSync],
    ),
    iosConfiguration: IosConfiguration(autoStart: false),
  );
}

/// Ask the running service to stop (used when the user toggles auto OFF).
void stopBackgroundService() {
  FlutterBackgroundService().invoke('stopService');
}

/// Start the service if it is not already running (used when toggling auto ON).
Future<void> startBackgroundService() async {
  final service = FlutterBackgroundService();
  if (!await service.isRunning()) {
    await service.startService();
  }
}

@pragma('vm:entry-point')
Future<void> onServiceStart(ServiceInstance service) async {
  WidgetsFlutterBinding.ensureInitialized();

  // Respect the user toggle even when the OS auto-starts us (e.g. after reboot).
  final prefs = await SharedPreferences.getInstance();
  await prefs.reload();
  if (!(prefs.getBool(_prefAuto) ?? true)) {
    await service.stopSelf();
    return;
  }

  service.on('stopService').listen((event) async {
    await service.stopSelf();
  });

  // Layer 1: best-effort real-time catch while this isolate is alive. Some OEMs
  // refuse to spin up the broadcast isolate — the poll below still covers us.
  try {
    Telephony.instance.listenIncomingSms(
      onNewMessage: (m) =>
          SmsListenerService.instance.handleIncomingMessage(m, fromBackground: true),
      onBackgroundMessage: mfsSmsBackgroundHandler,
      listenInBackground: true,
    );
  } catch (_) {
    // ignored — the periodic poll is the guarantee.
  }

  // Layer 2: guaranteed safety net — poll the inbox on a fixed cadence.
  Timer.periodic(_pollInterval, (timer) async {
    final p = await SharedPreferences.getInstance();
    await p.reload();
    if (!(p.getBool(_prefAuto) ?? true)) {
      timer.cancel();
      await service.stopSelf();
      return;
    }
    await SmsListenerService.instance.pollInboxForService();
  });

  // First sweep right away so a (re)start doesn't wait a full interval.
  await SmsListenerService.instance.pollInboxForService();
}
