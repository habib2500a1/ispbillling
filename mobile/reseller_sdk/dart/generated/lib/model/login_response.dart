//
// AUTO-GENERATED FILE, DO NOT MODIFY!
//
// @dart=2.18

// ignore_for_file: unused_element, unused_import
// ignore_for_file: always_put_required_named_parameters_first
// ignore_for_file: constant_identifier_names
// ignore_for_file: lines_longer_than_80_chars

part of isp_reseller_api;

class LoginResponse {
  /// Returns a new [LoginResponse] instance.
  LoginResponse({
    this.token,
    this.tokenType,
    this.authMode,
    this.actorType,
    this.permissions = const [],
    this.reseller = const {},
  });

  ///
  /// Please note: This property should have been non-nullable! Since the specification file
  /// does not include a default value (using the "default:" property), however, the generated
  /// source code must fall back to having a nullable type.
  /// Consider adding a "default:" property in the specification file to hide this note.
  ///
  String? token;

  ///
  /// Please note: This property should have been non-nullable! Since the specification file
  /// does not include a default value (using the "default:" property), however, the generated
  /// source code must fall back to having a nullable type.
  /// Consider adding a "default:" property in the specification file to hide this note.
  ///
  String? tokenType;

  ///
  /// Please note: This property should have been non-nullable! Since the specification file
  /// does not include a default value (using the "default:" property), however, the generated
  /// source code must fall back to having a nullable type.
  /// Consider adding a "default:" property in the specification file to hide this note.
  ///
  String? authMode;

  LoginResponseActorTypeEnum? actorType;

  List<String> permissions;

  Map<String, Object> reseller;

  @override
  bool operator ==(Object other) => identical(this, other) || other is LoginResponse &&
    other.token == token &&
    other.tokenType == tokenType &&
    other.authMode == authMode &&
    other.actorType == actorType &&
    _deepEquality.equals(other.permissions, permissions) &&
    _deepEquality.equals(other.reseller, reseller);

  @override
  int get hashCode =>
    // ignore: unnecessary_parenthesis
    (token == null ? 0 : token!.hashCode) +
    (tokenType == null ? 0 : tokenType!.hashCode) +
    (authMode == null ? 0 : authMode!.hashCode) +
    (actorType == null ? 0 : actorType!.hashCode) +
    (permissions.hashCode) +
    (reseller.hashCode);

  @override
  String toString() => 'LoginResponse[token=$token, tokenType=$tokenType, authMode=$authMode, actorType=$actorType, permissions=$permissions, reseller=$reseller]';

  Map<String, dynamic> toJson() {
    final json = <String, dynamic>{};
    if (this.token != null) {
      json[r'token'] = this.token;
    } else {
      json[r'token'] = null;
    }
    if (this.tokenType != null) {
      json[r'token_type'] = this.tokenType;
    } else {
      json[r'token_type'] = null;
    }
    if (this.authMode != null) {
      json[r'auth_mode'] = this.authMode;
    } else {
      json[r'auth_mode'] = null;
    }
    if (this.actorType != null) {
      json[r'actor_type'] = this.actorType;
    } else {
      json[r'actor_type'] = null;
    }
      json[r'permissions'] = this.permissions;
      json[r'reseller'] = this.reseller;
    return json;
  }

  /// Returns a new [LoginResponse] instance and imports its values from
  /// [value] if it's a [Map], null otherwise.
  // ignore: prefer_constructors_over_static_methods
  static LoginResponse? fromJson(dynamic value) {
    if (value is Map) {
      final json = value.cast<String, dynamic>();

      // Ensure that the map contains the required keys.
      // Note 1: the values aren't checked for validity beyond being non-null.
      // Note 2: this code is stripped in release mode!
      assert(() {
        requiredKeys.forEach((key) {
          assert(json.containsKey(key), 'Required key "LoginResponse[$key]" is missing from JSON.');
          assert(json[key] != null, 'Required key "LoginResponse[$key]" has a null value in JSON.');
        });
        return true;
      }());

      return LoginResponse(
        token: mapValueOfType<String>(json, r'token'),
        tokenType: mapValueOfType<String>(json, r'token_type'),
        authMode: mapValueOfType<String>(json, r'auth_mode'),
        actorType: LoginResponseActorTypeEnum.fromJson(json[r'actor_type']),
        permissions: json[r'permissions'] is Iterable
            ? (json[r'permissions'] as Iterable).cast<String>().toList(growable: false)
            : const [],
        reseller: mapCastOfType<String, Object>(json, r'reseller') ?? const {},
      );
    }
    return null;
  }

  static List<LoginResponse> listFromJson(dynamic json, {bool growable = false,}) {
    final result = <LoginResponse>[];
    if (json is List && json.isNotEmpty) {
      for (final row in json) {
        final value = LoginResponse.fromJson(row);
        if (value != null) {
          result.add(value);
        }
      }
    }
    return result.toList(growable: growable);
  }

  static Map<String, LoginResponse> mapFromJson(dynamic json) {
    final map = <String, LoginResponse>{};
    if (json is Map && json.isNotEmpty) {
      json = json.cast<String, dynamic>(); // ignore: parameter_assignments
      for (final entry in json.entries) {
        final value = LoginResponse.fromJson(entry.value);
        if (value != null) {
          map[entry.key] = value;
        }
      }
    }
    return map;
  }

  // maps a json object with a list of LoginResponse-objects as value to a dart map
  static Map<String, List<LoginResponse>> mapListFromJson(dynamic json, {bool growable = false,}) {
    final map = <String, List<LoginResponse>>{};
    if (json is Map && json.isNotEmpty) {
      // ignore: parameter_assignments
      json = json.cast<String, dynamic>();
      for (final entry in json.entries) {
        map[entry.key] = LoginResponse.listFromJson(entry.value, growable: growable,);
      }
    }
    return map;
  }

  /// The list of required keys that must be present in a JSON.
  static const requiredKeys = <String>{
  };
}


class LoginResponseActorTypeEnum {
  /// Instantiate a new enum with the provided [value].
  const LoginResponseActorTypeEnum._(this.value);

  /// The underlying value of this enum member.
  final String value;

  @override
  String toString() => value;

  String toJson() => value;

  static const owner = LoginResponseActorTypeEnum._(r'owner');
  static const staff = LoginResponseActorTypeEnum._(r'staff');

  /// List of all possible values in this [enum][LoginResponseActorTypeEnum].
  static const values = <LoginResponseActorTypeEnum>[
    owner,
    staff,
  ];

  static LoginResponseActorTypeEnum? fromJson(dynamic value) => LoginResponseActorTypeEnumTypeTransformer().decode(value);

  static List<LoginResponseActorTypeEnum> listFromJson(dynamic json, {bool growable = false,}) {
    final result = <LoginResponseActorTypeEnum>[];
    if (json is List && json.isNotEmpty) {
      for (final row in json) {
        final value = LoginResponseActorTypeEnum.fromJson(row);
        if (value != null) {
          result.add(value);
        }
      }
    }
    return result.toList(growable: growable);
  }
}

/// Transformation class that can [encode] an instance of [LoginResponseActorTypeEnum] to String,
/// and [decode] dynamic data back to [LoginResponseActorTypeEnum].
class LoginResponseActorTypeEnumTypeTransformer {
  factory LoginResponseActorTypeEnumTypeTransformer() => _instance ??= const LoginResponseActorTypeEnumTypeTransformer._();

  const LoginResponseActorTypeEnumTypeTransformer._();

  String encode(LoginResponseActorTypeEnum data) => data.value;

  /// Decodes a [dynamic value][data] to a LoginResponseActorTypeEnum.
  ///
  /// If [allowNull] is true and the [dynamic value][data] cannot be decoded successfully,
  /// then null is returned. However, if [allowNull] is false and the [dynamic value][data]
  /// cannot be decoded successfully, then an [UnimplementedError] is thrown.
  ///
  /// The [allowNull] is very handy when an API changes and a new enum value is added or removed,
  /// and users are still using an old app with the old code.
  LoginResponseActorTypeEnum? decode(dynamic data, {bool allowNull = true}) {
    if (data != null) {
      switch (data) {
        case r'owner': return LoginResponseActorTypeEnum.owner;
        case r'staff': return LoginResponseActorTypeEnum.staff;
        default:
          if (!allowNull) {
            throw ArgumentError('Unknown enum value to decode: $data');
          }
      }
    }
    return null;
  }

  /// Singleton [LoginResponseActorTypeEnumTypeTransformer] instance.
  static LoginResponseActorTypeEnumTypeTransformer? _instance;
}


