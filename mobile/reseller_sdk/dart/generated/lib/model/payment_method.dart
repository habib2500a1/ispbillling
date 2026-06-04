//
// AUTO-GENERATED FILE, DO NOT MODIFY!
//
// @dart=2.18

// ignore_for_file: unused_element, unused_import
// ignore_for_file: always_put_required_named_parameters_first
// ignore_for_file: constant_identifier_names
// ignore_for_file: lines_longer_than_80_chars

part of isp_reseller_api;


class PaymentMethod {
  /// Instantiate a new enum with the provided [value].
  const PaymentMethod._(this.value);

  /// The underlying value of this enum member.
  final String value;

  @override
  String toString() => value;

  String toJson() => value;

  static const cash = PaymentMethod._(r'cash');
  static const bkash = PaymentMethod._(r'bkash');
  static const nagad = PaymentMethod._(r'nagad');
  static const rocket = PaymentMethod._(r'rocket');
  static const bank = PaymentMethod._(r'bank');
  static const other = PaymentMethod._(r'other');

  /// List of all possible values in this [enum][PaymentMethod].
  static const values = <PaymentMethod>[
    cash,
    bkash,
    nagad,
    rocket,
    bank,
    other,
  ];

  static PaymentMethod? fromJson(dynamic value) => PaymentMethodTypeTransformer().decode(value);

  static List<PaymentMethod> listFromJson(dynamic json, {bool growable = false,}) {
    final result = <PaymentMethod>[];
    if (json is List && json.isNotEmpty) {
      for (final row in json) {
        final value = PaymentMethod.fromJson(row);
        if (value != null) {
          result.add(value);
        }
      }
    }
    return result.toList(growable: growable);
  }
}

/// Transformation class that can [encode] an instance of [PaymentMethod] to String,
/// and [decode] dynamic data back to [PaymentMethod].
class PaymentMethodTypeTransformer {
  factory PaymentMethodTypeTransformer() => _instance ??= const PaymentMethodTypeTransformer._();

  const PaymentMethodTypeTransformer._();

  String encode(PaymentMethod data) => data.value;

  /// Decodes a [dynamic value][data] to a PaymentMethod.
  ///
  /// If [allowNull] is true and the [dynamic value][data] cannot be decoded successfully,
  /// then null is returned. However, if [allowNull] is false and the [dynamic value][data]
  /// cannot be decoded successfully, then an [UnimplementedError] is thrown.
  ///
  /// The [allowNull] is very handy when an API changes and a new enum value is added or removed,
  /// and users are still using an old app with the old code.
  PaymentMethod? decode(dynamic data, {bool allowNull = true}) {
    if (data != null) {
      switch (data) {
        case r'cash': return PaymentMethod.cash;
        case r'bkash': return PaymentMethod.bkash;
        case r'nagad': return PaymentMethod.nagad;
        case r'rocket': return PaymentMethod.rocket;
        case r'bank': return PaymentMethod.bank;
        case r'other': return PaymentMethod.other;
        default:
          if (!allowNull) {
            throw ArgumentError('Unknown enum value to decode: $data');
          }
      }
    }
    return null;
  }

  /// Singleton [PaymentMethodTypeTransformer] instance.
  static PaymentMethodTypeTransformer? _instance;
}

