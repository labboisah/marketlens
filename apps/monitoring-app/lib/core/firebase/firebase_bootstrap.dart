import 'package:firebase_core/firebase_core.dart';
import 'package:firebase_crashlytics/firebase_crashlytics.dart';
import 'package:firebase_messaging/firebase_messaging.dart';

class FirebaseBootstrap {
  const FirebaseBootstrap._();

  static Future<void> initializeIfConfigured({bool enabled = false}) async {
    if (!enabled) {
      return;
    }

    await Firebase.initializeApp();
    await FirebaseMessaging.instance.setAutoInitEnabled(true);
    await FirebaseCrashlytics.instance.setCrashlyticsCollectionEnabled(false);
  }
}
