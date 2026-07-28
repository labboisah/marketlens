import 'package:flutter/material.dart';

@immutable
class MarketLensStatusColors extends ThemeExtension<MarketLensStatusColors> {
  const MarketLensStatusColors({
    required this.success,
    required this.onSuccess,
    required this.warning,
    required this.onWarning,
    required this.information,
    required this.onInformation,
    required this.offline,
    required this.onOffline,
  });

  final Color success;
  final Color onSuccess;
  final Color warning;
  final Color onWarning;
  final Color information;
  final Color onInformation;
  final Color offline;
  final Color onOffline;

  @override
  MarketLensStatusColors copyWith({
    Color? success,
    Color? onSuccess,
    Color? warning,
    Color? onWarning,
    Color? information,
    Color? onInformation,
    Color? offline,
    Color? onOffline,
  }) {
    return MarketLensStatusColors(
      success: success ?? this.success,
      onSuccess: onSuccess ?? this.onSuccess,
      warning: warning ?? this.warning,
      onWarning: onWarning ?? this.onWarning,
      information: information ?? this.information,
      onInformation: onInformation ?? this.onInformation,
      offline: offline ?? this.offline,
      onOffline: onOffline ?? this.onOffline,
    );
  }

  @override
  MarketLensStatusColors lerp(ThemeExtension<MarketLensStatusColors>? other, double t) {
    if (other is! MarketLensStatusColors) return this;

    return MarketLensStatusColors(
      success: Color.lerp(success, other.success, t)!,
      onSuccess: Color.lerp(onSuccess, other.onSuccess, t)!,
      warning: Color.lerp(warning, other.warning, t)!,
      onWarning: Color.lerp(onWarning, other.onWarning, t)!,
      information: Color.lerp(information, other.information, t)!,
      onInformation: Color.lerp(onInformation, other.onInformation, t)!,
      offline: Color.lerp(offline, other.offline, t)!,
      onOffline: Color.lerp(onOffline, other.onOffline, t)!,
    );
  }
}

class MarketLensTheme {
  const MarketLensTheme._();

  static const _radius = 8.0;

  static ThemeData light() {
    const scheme = ColorScheme(
      brightness: Brightness.light,
      primary: Color(0xFF0B1F3A),
      onPrimary: Color(0xFFFFFFFF),
      secondary: Color(0xFF2563EB),
      onSecondary: Color(0xFFFFFFFF),
      tertiary: Color(0xFF0F766E),
      onTertiary: Color(0xFFFFFFFF),
      error: Color(0xFFDC2626),
      onError: Color(0xFFFFFFFF),
      surface: Color(0xFFFFFFFF),
      onSurface: Color(0xFF0F172A),
    );

    return _base(
      scheme,
      scaffoldBackgroundColor: const Color(0xFFF8FAFC),
      statusColors: const MarketLensStatusColors(
        success: Color(0xFF059669),
        onSuccess: Color(0xFFFFFFFF),
        warning: Color(0xFFD97706),
        onWarning: Color(0xFFFFFFFF),
        information: Color(0xFF0284C7),
        onInformation: Color(0xFFFFFFFF),
        offline: Color(0xFFD97706),
        onOffline: Color(0xFFFFFFFF),
      ),
    );
  }

  static ThemeData dark() {
    const scheme = ColorScheme(
      brightness: Brightness.dark,
      primary: Color(0xFF93C5FD),
      onPrimary: Color(0xFF08111F),
      secondary: Color(0xFF38BDF8),
      onSecondary: Color(0xFF08111F),
      tertiary: Color(0xFF2DD4BF),
      onTertiary: Color(0xFF08111F),
      error: Color(0xFFF87171),
      onError: Color(0xFF08111F),
      surface: Color(0xFF0F1C2E),
      onSurface: Color(0xFFF8FAFC),
    );

    return _base(
      scheme,
      scaffoldBackgroundColor: const Color(0xFF08111F),
      statusColors: const MarketLensStatusColors(
        success: Color(0xFF34D399),
        onSuccess: Color(0xFF08111F),
        warning: Color(0xFFFBBF24),
        onWarning: Color(0xFF08111F),
        information: Color(0xFF7DD3FC),
        onInformation: Color(0xFF08111F),
        offline: Color(0xFFFBBF24),
        onOffline: Color(0xFF08111F),
      ),
    );
  }

  static ThemeData _base(
    ColorScheme scheme, {
    required Color scaffoldBackgroundColor,
    required MarketLensStatusColors statusColors,
  }) {
    return ThemeData(
      useMaterial3: true,
      colorScheme: scheme,
      scaffoldBackgroundColor: scaffoldBackgroundColor,
      extensions: [statusColors],
      cardTheme: CardThemeData(
        elevation: 0,
        shape: RoundedRectangleBorder(
          borderRadius: BorderRadius.circular(_radius),
          side: BorderSide(color: scheme.outlineVariant),
        ),
      ),
      inputDecorationTheme: InputDecorationTheme(
        filled: true,
        border: OutlineInputBorder(borderRadius: BorderRadius.circular(6)),
        enabledBorder: OutlineInputBorder(
          borderRadius: BorderRadius.circular(6),
          borderSide: BorderSide(color: scheme.outlineVariant),
        ),
        focusedBorder: OutlineInputBorder(
          borderRadius: BorderRadius.circular(6),
          borderSide: BorderSide(color: scheme.secondary, width: 2),
        ),
      ),
      snackBarTheme: SnackBarThemeData(
        behavior: SnackBarBehavior.floating,
        shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(6)),
      ),
    );
  }
}
