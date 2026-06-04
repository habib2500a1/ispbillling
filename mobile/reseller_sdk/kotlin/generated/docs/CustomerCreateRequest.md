
# CustomerCreateRequest

## Properties
| Name | Type | Description | Notes |
| ------------ | ------------- | ------------- | ------------- |
| **name** | **kotlin.String** |  |  |
| **phone** | **kotlin.String** |  |  |
| **address** | **kotlin.String** |  |  |
| **packageId** | **kotlin.Int** |  |  |
| **email** | **kotlin.String** |  |  [optional] |
| **areaId** | **kotlin.Int** |  |  [optional] |
| **zoneId** | **kotlin.Int** |  |  [optional] |
| **customerCode** | **kotlin.String** |  |  [optional] |
| **billingMode** | [**inline**](#BillingMode) |  |  [optional] |
| **billingDay** | **kotlin.Int** |  |  [optional] |
| **gracePeriodDays** | **kotlin.Int** |  |  [optional] |
| **joinedAt** | [**java.time.LocalDate**](java.time.LocalDate.md) |  |  [optional] |
| **notes** | **kotlin.String** |  |  [optional] |
| **provisionMikrotik** | **kotlin.Boolean** |  |  [optional] |
| **generateBill** | **kotlin.Boolean** |  |  [optional] |
| **collectPayment** | **kotlin.Boolean** |  |  [optional] |
| **paymentAmount** | [**java.math.BigDecimal**](java.math.BigDecimal.md) |  |  [optional] |
| **paymentMethod** | [**PaymentMethod**](PaymentMethod.md) |  |  [optional] |


<a id="BillingMode"></a>
## Enum: billing_mode
| Name | Value |
| ---- | ----- |
| billingMode | prepaid, postpaid |



