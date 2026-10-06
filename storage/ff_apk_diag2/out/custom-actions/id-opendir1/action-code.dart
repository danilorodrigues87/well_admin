import 'package:flutter/foundation.dart';
import 'package:url_launcher/url_launcher.dart';

Future openRotaDirections(String url) async {
  final trimmed = url.trim();
  if (trimmed.isEmpty) {
    return;
  }
  final uri = Uri.parse(trimmed);
  try {
    final ok = await launchUrl(
      uri,
      mode: kIsWeb ? LaunchMode.platformDefault : LaunchMode.externalApplication,
    );
    if (!ok) {
      await launchUrl(uri, mode: LaunchMode.platformDefault);
    }
  } catch (_) {
    await launchUrl(uri, mode: LaunchMode.platformDefault);
  }
}