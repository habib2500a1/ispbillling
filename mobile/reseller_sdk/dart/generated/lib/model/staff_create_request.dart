//
// AUTO-GENERATED FILE, DO NOT MODIFY!
//
// @dart=2.18

// ignore_for_file: unused_element, unused_import
// ignore_for_file: always_put_required_named_parameters_first
// ignore_for_file: constant_identifier_names
// ignore_for_file: lines_longer_than_80_chars

part of isp_reseller_api;

class StaffCreateRequest {
  /// Returns a new [StaffCreateRequest] instance.
  StaffCreateRequest({
    required this.name,
    required this.login,
    this.email,
    this.phone,
    required this.password,
    this.portalPermissions = const [],
    this.isActive,
  });

  String name;

  String login;

  ///
  /// Please note: This property should have been non-nullable! Since the specification file
  /// does not include a default value (using the "default:" property), however, the generated
  /// source code must fall back to having a nullable type.
  /// Consider adding a "default:" property in the specification file to hide this note.
  ///
  String? email;

  ///
  /// Please note: This property should have been non-nullable! Since the specification file
  /// does not include a default value (using the "default:" property), however, the generated
  /// source code must fall back to having a nullable type.
  /// Consider adding a "default:" property in the specification file to hide this note.
  ///
  String? phone;

  String password;

  List<String> portalPermissions;

  ///
  /// Please note: This property should have been non-nullable! Since the specification file
  /// does not include a default value (using the "default:" property), however, the generated
  /// source code must fall back to having a nullable type.
  /// Consider adding a "default:" property in the specification file to hide this note.
  ///
  bool? isActive;

  @override
  bool operator ==(Object other) => identical(this, other) || other is StaffCreateRequest &&
    other.name == name &&
    other.login == login &&
    other.email == email &&
    other.phone == phone &&
    other.password == password &&
    _deepEquality.equals(other.portalPermissions, portalPermissions) &&
    other.isActive == isActive;

  @override
  int get hashCode =>
    // ignore: unnecessary_parenthesis
    (name.hashCode) +
    (login.hashCode) +
    (email == null ? 0 : email!.hashCode) +
    (phone == null ? 0 : phone!.hashCode) +
    (password.hashCode) +
    (portalPermissions.hashCode) +
    (isActive == null ? 0 : isActive!.hashCode);

  @override
  String toString() => 'StaffCreateRequest[name=$name, login=$login, email=$email, phone=$phone, password=$password, portalPermissions=$portalPermissions, isActive=$isActive]';

  Map<String, dynamic> toJson() {
    final json = <String, dynamic>{};
      json[r'name'] = this.name;
      json[r'login'] = this.login;
    if (this.email != null) {
      json[r'email'] = this.email;
    } else {
      json[r'email'] = null;
    }
    if (this.phone != null) {
      json[r'phone'] = this.phone;
    } else {
      json[r'phone'] = null;
    }
      json[r'password'] = this.password;
      json[r'portal_permissions'] = this.portalPermissions;
    if (this.isActive != null) {
      json[r'is_active'] = this.isActive;
    } else {
      json[r'is_active'] = null;
    }
    return json;
  }

  /// Returns a new [StaffCreateRequest] instance and imports its values from
  /// [value] if it's a [Map], null otherwise.
  // ignore: prefer_constructors_over_static_methods
  static StaffCreateRequest? fromJson(dynamic value) {
    if (value is Map) {
      final json = value.cast<String, dynamic>();

      // Ensure that the map contains the required keys.
      // Note 1: the values aren't checked for validity beyond being non-null.
      // Note 2: this code is stripped in release mode!
      assert(() {
        requiredKeys.forEach((key) {
          assert(json.containsKey(key), 'Required key "StaffCreateRequest[$key]" is missing from JSON.');
          assert(json[key] != null, 'Required key "StaffCreateRequest[$key]" has a null value in JSON.');
        });
        return true;
      }());

      return StaffCreateRequest(
        name: mapValueOfType<String>(json, r'name')!,
        login: mapValueOfType<String>(json, r'login')!,
        email: mapValueOfType<String>(json, r'email'),
        phone: mapValueOfType<String>(json, r'phone'),
        password: mapValueOfType<String>(json, r'password')!,
        portalPermissions: json[r'portal_permissions'] is Iterable
            ? (json[r'portal_permissions'] as Iterable).cast<String>().toList(growable: false)
            : const [],
        isActive: mapValueOfType<bool>(json, r'is_active'),
      );
    }
    return null;
  }

  static List<StaffCreateRequest> listFromJson(dynamic json, {bool growable = false,}) {
    final result = <StaffCreateRequest>[];
    if (json is List && json.isNotEmpty) {
      for (final row in json) {
        final value = StaffCreateRequest.fromJson(row);
        if (value != null) {
          result.add(value);
        }
      }
    }
    return result.toList(growable: growable);
  }

  static Map<String, StaffCreateRequest> mapFromJson(dynamic json) {
    final map = <String, StaffCreateRequest>{};
    if (json is Map && json.isNotEmpty) {
      json = json.cast<String, dynamic>(); // ignore: parameter_assignments
      for (final entry in json.entries) {
        final value = StaffCreateRequest.fromJson(entry.value);
        if (value != null) {
          map[entry.key] = value;
        }
      }
    }
    return map;
  }

  // maps a json object with a list of StaffCreateRequest-objects as value to a dart map
  static Map<String, List<StaffCreateRequest>> mapListFromJson(dynamic json, {bool growable = false,}) {
    final map = <String, List<StaffCreateRequest>>{};
    if (json is Map && json.isNotEmpty) {
      // ignore: parameter_assignments
      json = json.cast<String, dynamic>();
      for (final entry in json.entries) {
        map[entry.key] = StaffCreateRequest.listFromJson(entry.value, growable: growable,);
      }
    }
    return map;
  }

  /// The list of required keys that must be present in a JSON.
  static const requiredKeys = <String>{
    'name',
    'login',
    'password',
  };
}

