//
// AUTO-GENERATED FILE, DO NOT MODIFY!
//
// @dart=2.18

// ignore_for_file: unused_element, unused_import
// ignore_for_file: always_put_required_named_parameters_first
// ignore_for_file: constant_identifier_names
// ignore_for_file: lines_longer_than_80_chars

part of isp_reseller_api;

class CustomerCreateRequest {
  /// Returns a new [CustomerCreateRequest] instance.
  CustomerCreateRequest({
    required this.name,
    required this.phone,
    this.email,
    required this.address,
    required this.packageId,
    this.areaId,
    this.zoneId,
    this.customerCode,
    this.billingMode,
    this.billingDay,
    this.gracePeriodDays,
    this.joinedAt,
    this.notes,
    this.provisionMikrotik,
    this.generateBill,
    this.collectPayment,
    this.paymentAmount,
    this.paymentMethod,
  });

  String name;

  String phone;

  ///
  /// Please note: This property should have been non-nullable! Since the specification file
  /// does not include a default value (using the "default:" property), however, the generated
  /// source code must fall back to having a nullable type.
  /// Consider adding a "default:" property in the specification file to hide this note.
  ///
  String? email;

  String address;

  int packageId;

  ///
  /// Please note: This property should have been non-nullable! Since the specification file
  /// does not include a default value (using the "default:" property), however, the generated
  /// source code must fall back to having a nullable type.
  /// Consider adding a "default:" property in the specification file to hide this note.
  ///
  int? areaId;

  ///
  /// Please note: This property should have been non-nullable! Since the specification file
  /// does not include a default value (using the "default:" property), however, the generated
  /// source code must fall back to having a nullable type.
  /// Consider adding a "default:" property in the specification file to hide this note.
  ///
  int? zoneId;

  ///
  /// Please note: This property should have been non-nullable! Since the specification file
  /// does not include a default value (using the "default:" property), however, the generated
  /// source code must fall back to having a nullable type.
  /// Consider adding a "default:" property in the specification file to hide this note.
  ///
  String? customerCode;

  CustomerCreateRequestBillingModeEnum? billingMode;

  /// Minimum value: 1
  /// Maximum value: 28
  ///
  /// Please note: This property should have been non-nullable! Since the specification file
  /// does not include a default value (using the "default:" property), however, the generated
  /// source code must fall back to having a nullable type.
  /// Consider adding a "default:" property in the specification file to hide this note.
  ///
  int? billingDay;

  /// Minimum value: 0
  /// Maximum value: 90
  ///
  /// Please note: This property should have been non-nullable! Since the specification file
  /// does not include a default value (using the "default:" property), however, the generated
  /// source code must fall back to having a nullable type.
  /// Consider adding a "default:" property in the specification file to hide this note.
  ///
  int? gracePeriodDays;

  ///
  /// Please note: This property should have been non-nullable! Since the specification file
  /// does not include a default value (using the "default:" property), however, the generated
  /// source code must fall back to having a nullable type.
  /// Consider adding a "default:" property in the specification file to hide this note.
  ///
  DateTime? joinedAt;

  ///
  /// Please note: This property should have been non-nullable! Since the specification file
  /// does not include a default value (using the "default:" property), however, the generated
  /// source code must fall back to having a nullable type.
  /// Consider adding a "default:" property in the specification file to hide this note.
  ///
  String? notes;

  ///
  /// Please note: This property should have been non-nullable! Since the specification file
  /// does not include a default value (using the "default:" property), however, the generated
  /// source code must fall back to having a nullable type.
  /// Consider adding a "default:" property in the specification file to hide this note.
  ///
  bool? provisionMikrotik;

  ///
  /// Please note: This property should have been non-nullable! Since the specification file
  /// does not include a default value (using the "default:" property), however, the generated
  /// source code must fall back to having a nullable type.
  /// Consider adding a "default:" property in the specification file to hide this note.
  ///
  bool? generateBill;

  ///
  /// Please note: This property should have been non-nullable! Since the specification file
  /// does not include a default value (using the "default:" property), however, the generated
  /// source code must fall back to having a nullable type.
  /// Consider adding a "default:" property in the specification file to hide this note.
  ///
  bool? collectPayment;

  ///
  /// Please note: This property should have been non-nullable! Since the specification file
  /// does not include a default value (using the "default:" property), however, the generated
  /// source code must fall back to having a nullable type.
  /// Consider adding a "default:" property in the specification file to hide this note.
  ///
  num? paymentAmount;

  ///
  /// Please note: This property should have been non-nullable! Since the specification file
  /// does not include a default value (using the "default:" property), however, the generated
  /// source code must fall back to having a nullable type.
  /// Consider adding a "default:" property in the specification file to hide this note.
  ///
  PaymentMethod? paymentMethod;

  @override
  bool operator ==(Object other) => identical(this, other) || other is CustomerCreateRequest &&
    other.name == name &&
    other.phone == phone &&
    other.email == email &&
    other.address == address &&
    other.packageId == packageId &&
    other.areaId == areaId &&
    other.zoneId == zoneId &&
    other.customerCode == customerCode &&
    other.billingMode == billingMode &&
    other.billingDay == billingDay &&
    other.gracePeriodDays == gracePeriodDays &&
    other.joinedAt == joinedAt &&
    other.notes == notes &&
    other.provisionMikrotik == provisionMikrotik &&
    other.generateBill == generateBill &&
    other.collectPayment == collectPayment &&
    other.paymentAmount == paymentAmount &&
    other.paymentMethod == paymentMethod;

  @override
  int get hashCode =>
    // ignore: unnecessary_parenthesis
    (name.hashCode) +
    (phone.hashCode) +
    (email == null ? 0 : email!.hashCode) +
    (address.hashCode) +
    (packageId.hashCode) +
    (areaId == null ? 0 : areaId!.hashCode) +
    (zoneId == null ? 0 : zoneId!.hashCode) +
    (customerCode == null ? 0 : customerCode!.hashCode) +
    (billingMode == null ? 0 : billingMode!.hashCode) +
    (billingDay == null ? 0 : billingDay!.hashCode) +
    (gracePeriodDays == null ? 0 : gracePeriodDays!.hashCode) +
    (joinedAt == null ? 0 : joinedAt!.hashCode) +
    (notes == null ? 0 : notes!.hashCode) +
    (provisionMikrotik == null ? 0 : provisionMikrotik!.hashCode) +
    (generateBill == null ? 0 : generateBill!.hashCode) +
    (collectPayment == null ? 0 : collectPayment!.hashCode) +
    (paymentAmount == null ? 0 : paymentAmount!.hashCode) +
    (paymentMethod == null ? 0 : paymentMethod!.hashCode);

  @override
  String toString() => 'CustomerCreateRequest[name=$name, phone=$phone, email=$email, address=$address, packageId=$packageId, areaId=$areaId, zoneId=$zoneId, customerCode=$customerCode, billingMode=$billingMode, billingDay=$billingDay, gracePeriodDays=$gracePeriodDays, joinedAt=$joinedAt, notes=$notes, provisionMikrotik=$provisionMikrotik, generateBill=$generateBill, collectPayment=$collectPayment, paymentAmount=$paymentAmount, paymentMethod=$paymentMethod]';

  Map<String, dynamic> toJson() {
    final json = <String, dynamic>{};
      json[r'name'] = this.name;
      json[r'phone'] = this.phone;
    if (this.email != null) {
      json[r'email'] = this.email;
    } else {
      json[r'email'] = null;
    }
      json[r'address'] = this.address;
      json[r'package_id'] = this.packageId;
    if (this.areaId != null) {
      json[r'area_id'] = this.areaId;
    } else {
      json[r'area_id'] = null;
    }
    if (this.zoneId != null) {
      json[r'zone_id'] = this.zoneId;
    } else {
      json[r'zone_id'] = null;
    }
    if (this.customerCode != null) {
      json[r'customer_code'] = this.customerCode;
    } else {
      json[r'customer_code'] = null;
    }
    if (this.billingMode != null) {
      json[r'billing_mode'] = this.billingMode;
    } else {
      json[r'billing_mode'] = null;
    }
    if (this.billingDay != null) {
      json[r'billing_day'] = this.billingDay;
    } else {
      json[r'billing_day'] = null;
    }
    if (this.gracePeriodDays != null) {
      json[r'grace_period_days'] = this.gracePeriodDays;
    } else {
      json[r'grace_period_days'] = null;
    }
    if (this.joinedAt != null) {
      json[r'joined_at'] = _dateFormatter.format(this.joinedAt!.toUtc());
    } else {
      json[r'joined_at'] = null;
    }
    if (this.notes != null) {
      json[r'notes'] = this.notes;
    } else {
      json[r'notes'] = null;
    }
    if (this.provisionMikrotik != null) {
      json[r'provision_mikrotik'] = this.provisionMikrotik;
    } else {
      json[r'provision_mikrotik'] = null;
    }
    if (this.generateBill != null) {
      json[r'generate_bill'] = this.generateBill;
    } else {
      json[r'generate_bill'] = null;
    }
    if (this.collectPayment != null) {
      json[r'collect_payment'] = this.collectPayment;
    } else {
      json[r'collect_payment'] = null;
    }
    if (this.paymentAmount != null) {
      json[r'payment_amount'] = this.paymentAmount;
    } else {
      json[r'payment_amount'] = null;
    }
    if (this.paymentMethod != null) {
      json[r'payment_method'] = this.paymentMethod;
    } else {
      json[r'payment_method'] = null;
    }
    return json;
  }

  /// Returns a new [CustomerCreateRequest] instance and imports its values from
  /// [value] if it's a [Map], null otherwise.
  // ignore: prefer_constructors_over_static_methods
  static CustomerCreateRequest? fromJson(dynamic value) {
    if (value is Map) {
      final json = value.cast<String, dynamic>();

      // Ensure that the map contains the required keys.
      // Note 1: the values aren't checked for validity beyond being non-null.
      // Note 2: this code is stripped in release mode!
      assert(() {
        requiredKeys.forEach((key) {
          assert(json.containsKey(key), 'Required key "CustomerCreateRequest[$key]" is missing from JSON.');
          assert(json[key] != null, 'Required key "CustomerCreateRequest[$key]" has a null value in JSON.');
        });
        return true;
      }());

      return CustomerCreateRequest(
        name: mapValueOfType<String>(json, r'name')!,
        phone: mapValueOfType<String>(json, r'phone')!,
        email: mapValueOfType<String>(json, r'email'),
        address: mapValueOfType<String>(json, r'address')!,
        packageId: mapValueOfType<int>(json, r'package_id')!,
        areaId: mapValueOfType<int>(json, r'area_id'),
        zoneId: mapValueOfType<int>(json, r'zone_id'),
        customerCode: mapValueOfType<String>(json, r'customer_code'),
        billingMode: CustomerCreateRequestBillingModeEnum.fromJson(json[r'billing_mode']),
        billingDay: mapValueOfType<int>(json, r'billing_day'),
        gracePeriodDays: mapValueOfType<int>(json, r'grace_period_days'),
        joinedAt: mapDateTime(json, r'joined_at', r''),
        notes: mapValueOfType<String>(json, r'notes'),
        provisionMikrotik: mapValueOfType<bool>(json, r'provision_mikrotik'),
        generateBill: mapValueOfType<bool>(json, r'generate_bill'),
        collectPayment: mapValueOfType<bool>(json, r'collect_payment'),
        paymentAmount: num.parse('${json[r'payment_amount']}'),
        paymentMethod: PaymentMethod.fromJson(json[r'payment_method']),
      );
    }
    return null;
  }

  static List<CustomerCreateRequest> listFromJson(dynamic json, {bool growable = false,}) {
    final result = <CustomerCreateRequest>[];
    if (json is List && json.isNotEmpty) {
      for (final row in json) {
        final value = CustomerCreateRequest.fromJson(row);
        if (value != null) {
          result.add(value);
        }
      }
    }
    return result.toList(growable: growable);
  }

  static Map<String, CustomerCreateRequest> mapFromJson(dynamic json) {
    final map = <String, CustomerCreateRequest>{};
    if (json is Map && json.isNotEmpty) {
      json = json.cast<String, dynamic>(); // ignore: parameter_assignments
      for (final entry in json.entries) {
        final value = CustomerCreateRequest.fromJson(entry.value);
        if (value != null) {
          map[entry.key] = value;
        }
      }
    }
    return map;
  }

  // maps a json object with a list of CustomerCreateRequest-objects as value to a dart map
  static Map<String, List<CustomerCreateRequest>> mapListFromJson(dynamic json, {bool growable = false,}) {
    final map = <String, List<CustomerCreateRequest>>{};
    if (json is Map && json.isNotEmpty) {
      // ignore: parameter_assignments
      json = json.cast<String, dynamic>();
      for (final entry in json.entries) {
        map[entry.key] = CustomerCreateRequest.listFromJson(entry.value, growable: growable,);
      }
    }
    return map;
  }

  /// The list of required keys that must be present in a JSON.
  static const requiredKeys = <String>{
    'name',
    'phone',
    'address',
    'package_id',
  };
}


class CustomerCreateRequestBillingModeEnum {
  /// Instantiate a new enum with the provided [value].
  const CustomerCreateRequestBillingModeEnum._(this.value);

  /// The underlying value of this enum member.
  final String value;

  @override
  String toString() => value;

  String toJson() => value;

  static const prepaid = CustomerCreateRequestBillingModeEnum._(r'prepaid');
  static const postpaid = CustomerCreateRequestBillingModeEnum._(r'postpaid');

  /// List of all possible values in this [enum][CustomerCreateRequestBillingModeEnum].
  static const values = <CustomerCreateRequestBillingModeEnum>[
    prepaid,
    postpaid,
  ];

  static CustomerCreateRequestBillingModeEnum? fromJson(dynamic value) => CustomerCreateRequestBillingModeEnumTypeTransformer().decode(value);

  static List<CustomerCreateRequestBillingModeEnum> listFromJson(dynamic json, {bool growable = false,}) {
    final result = <CustomerCreateRequestBillingModeEnum>[];
    if (json is List && json.isNotEmpty) {
      for (final row in json) {
        final value = CustomerCreateRequestBillingModeEnum.fromJson(row);
        if (value != null) {
          result.add(value);
        }
      }
    }
    return result.toList(growable: growable);
  }
}

/// Transformation class that can [encode] an instance of [CustomerCreateRequestBillingModeEnum] to String,
/// and [decode] dynamic data back to [CustomerCreateRequestBillingModeEnum].
class CustomerCreateRequestBillingModeEnumTypeTransformer {
  factory CustomerCreateRequestBillingModeEnumTypeTransformer() => _instance ??= const CustomerCreateRequestBillingModeEnumTypeTransformer._();

  const CustomerCreateRequestBillingModeEnumTypeTransformer._();

  String encode(CustomerCreateRequestBillingModeEnum data) => data.value;

  /// Decodes a [dynamic value][data] to a CustomerCreateRequestBillingModeEnum.
  ///
  /// If [allowNull] is true and the [dynamic value][data] cannot be decoded successfully,
  /// then null is returned. However, if [allowNull] is false and the [dynamic value][data]
  /// cannot be decoded successfully, then an [UnimplementedError] is thrown.
  ///
  /// The [allowNull] is very handy when an API changes and a new enum value is added or removed,
  /// and users are still using an old app with the old code.
  CustomerCreateRequestBillingModeEnum? decode(dynamic data, {bool allowNull = true}) {
    if (data != null) {
      switch (data) {
        case r'prepaid': return CustomerCreateRequestBillingModeEnum.prepaid;
        case r'postpaid': return CustomerCreateRequestBillingModeEnum.postpaid;
        default:
          if (!allowNull) {
            throw ArgumentError('Unknown enum value to decode: $data');
          }
      }
    }
    return null;
  }

  /// Singleton [CustomerCreateRequestBillingModeEnumTypeTransformer] instance.
  static CustomerCreateRequestBillingModeEnumTypeTransformer? _instance;
}


