/// Staff sub-interfaces inside the unified APK (same token, different UX shells).
abstract final class StaffInterface {
  static const admin = 'admin';
  static const collector = 'collector';
  static const noc = 'noc';
  static const technician = 'technician';

  static const labels = {
    admin: 'Staff / Admin',
    collector: 'Collector',
    noc: 'NOC',
    technician: 'Technician',
  };

  static String labelFor(String id) => labels[id] ?? id;

  static bool isTechnicianShell(String mode) => mode == technician;
}
