class PushNotificationPolicy {
  const PushNotificationPolicy._();

  static const hideSensitiveFinancialDetailsOnLockScreen = true;

  static String safeLockedScreenBody(String category) {
    return 'MarketLens $category update available.';
  }
}
