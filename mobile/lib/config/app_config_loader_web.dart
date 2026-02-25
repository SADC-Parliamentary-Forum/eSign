// Web: no asset load (avoids assets/assets/config.json 404). Returns defaults.

Future<Map<String, dynamic>> loadConfigJson() async {
  return {
    'apiBaseUrl': 'https://esign.sadcpf.org/api',
    'features': {'biometricSigning': true},
    'network': {'timeoutMs': 10000},
  };
}
