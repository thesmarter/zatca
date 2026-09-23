[English](README.md) | العربية

<div align="center">

# 🧾 الفوترة الإلكترونية لهيئة الزكاة والضريبة والجمارك — المرحلة الثانية

### حزمة PHP للتكامل مع نظام الفوترة الإلكترونية (فاتورة) في المملكة العربية السعودية

تُبسّط متطلبات المرحلة الثانية للفوترة الإلكترونية، بما في ذلك إنشاء الشهادات، وتوقيع الفواتير، وتوليد رمز الاستجابة السريعة (QR)، وإرسال الفواتير إلى واجهة ZATCA البرمجية

[![Latest Version](https://img.shields.io/packagist/v/thesmarter/zatca?style=flat-square)](https://packagist.org/packages/thesmarter/zatca)
[![License](https://img.shields.io/packagist/l/thesmarter/zatca?style=flat-square)](LICENSE)
[![PHP Version](https://img.shields.io/packagist/php-v/thesmarter/zatca?style=flat-square)](composer.json)
[![Tests](https://github.com/thesmarter/zatca/actions/workflows/tests.yml/badge.svg)](https://github.com/thesmarter/zatca/actions)

**طُوّرت بـ ❤️ بواسطة [فريق Smart](https://smart.sd)**

[عرض الأمثلة](https://github.com/thesmarter/zatca/tree/main/examples) • [الإبلاغ عن خطأ](https://github.com/thesmarter/zatca/issues)

---

</div>

## جدول المحتويات

- [الميزات](#الميزات)
- [المتطلبات](#المتطلبات)
- [التثبيت](#التثبيت)
- [البدء السريع](#البدء-السريع)
  - [1. إنشاء طلب توقيع الشهادة (CSR)](#1-إنشاء-طلب-توقيع-الشهادة-csr)
  - [2. طلب شهادة الامتثال](#2-طلب-شهادة-الامتثال)
  - [3. تشفير (Hash) ملف الفاتورة XML](#3-تشفير-hash-ملف-الفاتورة-xml)
  - [4. توقيع الفاتورة](#4-توقيع-الفاتورة)
  - [5. التحقق من الامتثال](#5-التحقق-من-الامتثال)
  - [6. إنشاء شهادة الإنتاج](#6-إنشاء-شهادة-الإنتاج)
  - [7. إرسال الفاتورة إلى ZATCA](#7-إرسال-الفاتورة-إلى-zatca)
- [إعداد البيئات](#إعداد-البيئات)
- [استخدام الواجهة الموحدة (Facade)](#استخدام-الواجهة-الموحدة-facade)
- [أنواع الفواتير](#أنواع-الفواتير)
- [مرجع الواجهة البرمجية API](#مرجع-الواجهة-البرمجية-api)
- [بناء فواتير UBL من المصفوفات](#بناء-فواتير-ubl-من-المصفوفات)
- [تجديد شهادة الإنتاج](#تجديد-شهادة-الإنتاج)
- [الأمثلة](#الأمثلة)
- [الاختبارات](#الاختبارات)
- [معالجة الأخطاء](#معالجة-الأخطاء)
- [أفضل الممارسات](#أفضل-الممارسات)
- [سير عمل التكامل مع ZATCA](#سير-عمل-التكامل-مع-zatca)
- [المصادر](#المصادر)
- [المساهمة](#المساهمة)
- [الرخصة](#الرخصة)
- [الشكر والتقدير](#الشكر-والتقدير)
- [الدعم](#الدعم)

## الميزات

- **إدارة الشهادات**: إنشاء طلب توقيع الشهادة (CSR) والحصول على شهادات الامتثال والإنتاج، بما في ذلك **تجديد** شهادة الإنتاج (CSID)
- **منشئ فواتير UBL**: بناء الفواتير القياسية والمبسطة وإشعارات الدائن والمدين (388/383/381) من مصفوفات PHP عادية مع حساب المجاميع ومجموعات ضريبة القيمة المضافة تلقائيًا
- **معالجة الفواتير**: تشفير وتوقيع فواتير XML وفقًا لمواصفات ZATCA (توقيع DOM يراعي نطاقات الأسماء)
- **توليد رمز QR**: إنشاء رموز QR متوافقة للفواتير المبسطة والقياسية
- **التحقق من الامتثال**: فحص امتثال الفاتورة قبل الإرسال إلى بيئة الإنتاج
- **إرسال الفواتير**: إرسال الفواتير إلى ZATCA عبر واجهة الإبلاغ (للفواتير المبسطة) أو التخليص (للفواتير القياسية)
- **دعم بيئات متعددة**: بيئات Sandbox وSimulation وProduction
- **بنية نظيفة**: كود منظم وقابل للصيانة والاختبار

## المتطلبات

- PHP **>= 8.1**
- إضافات PHP المطلوبة:
  - `ext-openssl`
  - `ext-dom`
  - `ext-xsl`
  - `ext-json`
  - `ext-bcmath`
  - `ext-simplexml`
- [Composer](https://getcomposer.org/) لإدارة الاعتماديات

## التثبيت

ثبّت الحزمة عبر Composer:

```bash
composer require thesmarter/zatca
```

## البدء السريع

### 1. إنشاء طلب توقيع الشهادة (CSR)

أولًا، أنشئ طلب توقيع شهادة (CSR) ومفتاحًا خاصًا لمنشأتك:

```php
<?php
require_once 'vendor/autoload.php';

use Smart\Zatca\CertificateSigningRequestBuilder;

$csrBuilder = new CertificateSigningRequestBuilder();

$csrBuilder
    ->setCommonName('TST-886431145-311111111101113')
    ->setSerialNumber('TST', 'TST', 'ed22f1d8-e6a2-1118-9b58-d9a8f11e445f')
    ->setOrganizationIdentifier('311111111101113')
    ->setOrganizationalUnitName('Riyadh Branch')
    ->setOrganizationName('ABCD Limited')
    ->setCountry('SA')
    ->setInvoiceType('1100')
    ->setAddress('RRRD2929')
    ->setBusinessCategory('Technology')
    ->generate();

// حفظ CSR والمفتاح الخاص
$csrBuilder->saveCsr('certificate.csr');
$csrBuilder->savePrivateKey('private.pem');
```

### 2. طلب شهادة الامتثال

احصل على شهادة امتثال من ZATCA باستخدام CSR ورمز التحقق (OTP):

```php
<?php
use Smart\Zatca\ComplianceService;
use Smart\Zatca\Enums\ZatcaEnvironment;

$complianceService = new ComplianceService(ZatcaEnvironment::SANDBOX);

$csr = file_get_contents('certificate.csr');
$ccsid = $complianceService->requestComplianceCertificate(
    b64Csr: base64_encode($csr),
    otp: '123456'
);

// حفظ شهادة الامتثال
$ccsid->saveAsJson('ccsid.json');

echo "Certificate: " . $ccsid->certificate . PHP_EOL;
echo "Secret: " . $ccsid->secret . PHP_EOL;
```

### 3. تشفير (Hash) ملف الفاتورة XML

شفّر ملف الفاتورة XML غير الموقّع:

```php
<?php
use Smart\Zatca\InvoiceHashingService;

$xmlContent = file_get_contents('unsigned_invoice.xml');

$hashingService = new InvoiceHashingService();
$result = $hashingService->hash($xmlContent);

echo "Invoice Hash: " . $result->invoiceHash . PHP_EOL;
echo "UUID: " . $result->uuid . PHP_EOL;

// حفظ النتيجة لاستخدامها لاحقًا
file_put_contents('invoice.json', json_encode($result, JSON_PRETTY_PRINT));
```

### 4. توقيع الفاتورة

وقّع الفاتورة باستخدام مفتاحك الخاص وشهادة الامتثال:

```php
<?php
use Smart\Zatca\InvoiceSigningService;
use Smart\Zatca\GetDigitalSignatureService;
use Smart\Zatca\GetPublicKeyAndSignatureService;
use Smart\Zatca\QrCodeGeneratorService;
use Smart\Zatca\Entities\CSID;

$invoiceData = json_decode(file_get_contents('invoice.json'), true);
$canonicalXml = base64_decode($invoiceData['base64CanonicalXml']);
$invoiceHash = $invoiceData['invoiceHash'];

$ccsid = CSID::loadFromJson('ccsid.json');
$privateKey = file_get_contents('private.pem');
$privateKey = str_replace(["\n", "\t", "-----BEGIN PRIVATE KEY-----", "-----END PRIVATE KEY-----"], '', $privateKey);

$digitalSignatureService = new GetDigitalSignatureService();
$publicKeyService = new GetPublicKeyAndSignatureService();
$qrCodeService = new QrCodeGeneratorService($publicKeyService);

$signingService = new InvoiceSigningService($digitalSignatureService, $qrCodeService);

$result = $signingService->sign(
    csid: $ccsid,
    privateKeyContent: $privateKey,
    canonicalXml: $canonicalXml,
    invoiceHash: $invoiceHash
);

echo "Signed Invoice (Base64): " . $result->b64SignedInvoice . PHP_EOL;
echo "QR Code (Base64): " . $result->b64QrCode . PHP_EOL;
```

### 5. التحقق من الامتثال

تحقق من فاتورتك الموقعة مقابل فحوصات الامتثال الخاصة بـ ZATCA:

```php
<?php
use Smart\Zatca\ComplianceService;
use Smart\Zatca\Enums\ZatcaEnvironment;
use Smart\Zatca\Entities\CSID;

$complianceService = new ComplianceService(ZatcaEnvironment::SANDBOX);
$ccsid = CSID::loadFromJson('ccsid.json');

$result = $complianceService->checkCompliance(
    binarySecurityToken: $ccsid->certificate,
    secret: $ccsid->secret,
    invoiceHash: 'YOUR_INVOICE_HASH',
    invoiceUuid: 'YOUR_INVOICE_UUID',
    signedInvoice: 'BASE64_SIGNED_INVOICE'
);

echo "Validation Status: " . $result->validationResults->status . PHP_EOL;
echo "Clearance Status: " . $result->clearanceStatus . PHP_EOL;

if (!empty($result->validationResults->errorMessages)) {
    echo "Errors:" . PHP_EOL;
    print_r($result->validationResults->errorMessages);
}
```

### 6. إنشاء شهادة الإنتاج

بعد نجاح التحقق من الامتثال، اطلب شهادة الإنتاج:

```php
<?php
use Smart\Zatca\ProductionCsidGeneratorService;
use Smart\Zatca\Enums\ZatcaEnvironment;
use Smart\Zatca\Entities\CSID;

$productionService = new ProductionCsidGeneratorService(ZatcaEnvironment::SANDBOX);
$ccsid = CSID::loadFromJson('ccsid.json');

$pcsid = $productionService->requestProductionCertificate(
    binarySecurityToken: $ccsid->certificate,
    secret: $ccsid->secret,
    ccsidRequestId: $ccsid->requestId
);

// حفظ شهادة الإنتاج
$pcsid->saveAsJson('pcsid.json');
```

### 7. إرسال الفاتورة إلى ZATCA

أرسل فاتورتك الموقعة إلى ZATCA (الإبلاغ للفواتير المبسطة، والتخليص للفواتير القياسية):

```php
<?php
use Smart\Zatca\InvoiceSubmissionService;
use Smart\Zatca\Enums\ZatcaEnvironment;
use Smart\Zatca\Entities\CSID;

$submissionService = new InvoiceSubmissionService(ZatcaEnvironment::PRODUCTION);
$pcsid = CSID::loadFromJson('pcsid.json');

$response = $submissionService->submit(
    csid: $pcsid,
    isSimplified: true, // ضع false للفواتير القياسية
    invoiceHash: 'YOUR_INVOICE_HASH',
    invoiceUuid: 'YOUR_INVOICE_UUID',
    invoiceXml: 'YOUR_SIGNED_INVOICE_XML'
);

if ($response->isSubmitted) {
    echo "Invoice submitted successfully!" . PHP_EOL;
    echo "Status: " . $response->status . PHP_EOL;
} else {
    echo "Submission failed!" . PHP_EOL;
    print_r($response->validationResults->errorMessages);
}
```

## إعداد البيئات

تدعم الحزمة ثلاث بيئات من ZATCA:

```php
use Smart\Zatca\Enums\ZatcaEnvironment;

// Sandbox - للتطوير والاختبار
ZatcaEnvironment::SANDBOX

// Simulation - لاختبار ما قبل الإنتاج
ZatcaEnvironment::SIMULATION

// Production - للفواتير الحقيقية
ZatcaEnvironment::PRODUCTION
```

## استخدام الواجهة الموحدة (Facade)

توفر واجهة `Zatca` طريقة أنظف وأسهل للوصول إلى جميع الخدمات. فبدلًا من إنشاء الخدمات واعتمادياتها يدويًا، يمكنك استخدام الواجهة كنقطة دخول واحدة.

### مزايا استخدام الواجهة الموحدة

- **واجهة مبسطة**: نقطة دخول واحدة لجميع عمليات ZATCA
- **التحميل الكسول**: لا تُنشأ الخدمات إلا عند الحاجة إليها
- **إدارة الاعتماديات**: تتولى حقن الاعتماديات تلقائيًا
- **كود أنظف**: تقليل الكود المتكرر وقابلية قراءة أعلى

### مثال الواجهة: سير العمل الكامل

```php
<?php
require_once 'vendor/autoload.php';

use Smart\Zatca\Zatca;
use Smart\Zatca\Enums\ZatcaEnvironment;

// تهيئة الواجهة
$zatca = new Zatca(ZatcaEnvironment::SANDBOX);

// 1. إنشاء CSR
$csrBuilder = $zatca->csrBuilder()
    ->setCommonName('TST-886431145-311111111101113')
    ->setSerialNumber('TST', 'TST', 'ed22f1d8-e6a2-1118-9b58-d9a8f11e445f')
    ->setOrganizationIdentifier('311111111101113')
    ->setOrganizationalUnitName('Riyadh Branch')
    ->setOrganizationName('ABCD Limited')
    ->setCountry('SA')
    ->setInvoiceType('1100')
    ->setAddress('RRRD2929')
    ->setBusinessCategory('Technology')
    ->generate();

$csrBuilder->saveCsr('certificate.csr');
$csrBuilder->savePrivateKey('private.pem');

// 2. طلب شهادة الامتثال
$csr = file_get_contents('certificate.csr');
$ccsid = $zatca->compliance()->requestComplianceCertificate(
    b64Csr: base64_encode($csr),
    otp: '123456'
);
$ccsid->saveAsJson('ccsid.json');

// 3. تشفير الفاتورة
$invoiceXml = file_get_contents('unsigned_invoice.xml');
$hashingResult = $zatca->hashing()->hash($invoiceXml);

// 4. توقيع الفاتورة
$privateKey = file_get_contents('private.pem');
$privateKey = str_replace(["\n", "\t", "-----BEGIN PRIVATE KEY-----", "-----END PRIVATE KEY-----"], '', $privateKey);

$signingResult = $zatca->signing()->sign(
    csid: $ccsid,
    privateKeyContent: $privateKey,
    canonicalXml: base64_decode($hashingResult->b64CanonicalXml),
    invoiceHash: $hashingResult->invoiceHash
);

// 5. التحقق من الامتثال
$validationResponse = $zatca->compliance()->checkCompliance(
    binarySecurityToken: $ccsid->certificate,
    secret: $ccsid->secret,
    invoiceHash: $hashingResult->invoiceHash,
    invoiceUuid: $hashingResult->uuid,
    signedInvoice: $signingResult->b64SignedInvoice
);

if ($validationResponse->validationResults->status === 'PASS') {
    // 6. طلب شهادة الإنتاج
    $pcsid = $zatca->production()->requestProductionCertificate(
        binarySecurityToken: $ccsid->certificate,
        secret: $ccsid->secret,
        ccsidRequestId: $ccsid->requestId
    );
    $pcsid->saveAsJson('pcsid.json');

    // 7. إرسال الفاتورة إلى الإنتاج
    $submissionResult = $zatca->submission()->submit(
        csid: $pcsid,
        isSimplified: true,
        invoiceHash: $hashingResult->invoiceHash,
        invoiceUuid: $hashingResult->uuid,
        invoiceXml: base64_decode($signingResult->b64SignedInvoice)
    );

    if ($submissionResult->isSubmitted) {
        echo "Invoice submitted successfully!" . PHP_EOL;
    }
}
```

### أساليب الواجهة الموحدة API

توفر واجهة `Zatca` الأساليب التالية:

```php
// الوصول إلى الخدمات
$zatca->compliance()      // ComplianceService
$zatca->production()      // ProductionCsidGeneratorService
$zatca->submission()      // InvoiceSubmissionService
$zatca->hashing()         // InvoiceHashingService
$zatca->signing()         // InvoiceSigningService
$zatca->qrCode()          // QrCodeGeneratorService
$zatca->csrBuilder()      // CertificateSigningRequestBuilder
$zatca->invoiceBuilder()  // InvoiceBuilder (منشئ فواتير UBL)
$zatca->client()          // ZatcaClient (للوصول المباشر إلى API)
```

### مقارنة: الطريقة التقليدية مقابل الواجهة

**الطريقة التقليدية:**

```php
// حقن الاعتماديات يدويًا
$digitalSignatureService = new GetDigitalSignatureService();
$publicKeyService = new GetPublicKeyAndSignatureService();
$qrCodeService = new QrCodeGeneratorService($publicKeyService);
$signingService = new InvoiceSigningService($digitalSignatureService, $qrCodeService);

$result = $signingService->sign(...);
```

**طريقة الواجهة:**

```php
// نظيفة وبسيطة
$zatca = new Zatca(ZatcaEnvironment::SANDBOX);
$result = $zatca->signing()->sign(...);
```

تتولى الواجهة حقن جميع الاعتماديات تلقائيًا، مما يجعل الكود أنظف وأسهل في الصيانة.

## أنواع الفواتير

عند إنشاء CSR، حدد نوع الفاتورة باستخدام رمز مكون من 4 أرقام:

- `1100` - الفواتير القياسية والمبسطة معًا
- `0100` - الفواتير المبسطة فقط (B2C)
- `1000` - الفواتير القياسية فقط (B2B)

كل رقم يعمل كعلامة منطقية (boolean): `[قياسية، مبسطة، للاستخدام المستقبلي، للاستخدام المستقبلي]`

## مرجع الواجهة البرمجية API

### الخدمات الأساسية

#### CertificateSigningRequestBuilder

إنشاء CSR والمفتاح الخاص للانضمام إلى ZATCA.

**الأساليب:**

- `setCommonName(string $name)` - تعيين الاسم الشائع
- `setSerialNumber(string $solutionProvider, string $solutionName, string $serialNumber)` - تعيين الرقم التسلسلي للجهاز
- `setOrganizationIdentifier(string $id)` - تعيين الرقم الضريبي للمنشأة
- `setOrganizationalUnitName(string $name)` - تعيين اسم الوحدة التنظيمية
- `setOrganizationName(string $name)` - تعيين اسم المنشأة
- `setCountry(string $country)` - تعيين رمز الدولة (SA)
- `setInvoiceType(string $type)` - تعيين نوع الفاتورة (مثل '1100')
- `setAddress(string $address)` - تعيين عنوان المنشأة
- `setBusinessCategory(string $category)` - تعيين فئة النشاط التجاري
- `generate()` - إنشاء CSR والمفتاح الخاص
- `getCsr()` - الحصول على CSR المُنشأ
- `getPrivateKey()` - الحصول على المفتاح الخاص المُنشأ
- `saveCsr(string $path)` - حفظ CSR في ملف
- `savePrivateKey(string $path)` - حفظ المفتاح الخاص في ملف

#### ComplianceService

معالجة طلبات شهادات الامتثال والتحقق منها.

**الأساليب:**

- `requestComplianceCertificate(string $b64Csr, string $otp): CSID`
- `checkCompliance(string $binarySecurityToken, string $secret, string $invoiceHash, string $invoiceUuid, string $signedInvoice): ValidationResponse`

#### InvoiceHashingService

تشفير فواتير XML وفقًا لمواصفات ZATCA.

**الأساليب:**

- `hash(string $unsignedInvoiceXml): InvoiceHashingResult`

#### InvoiceSigningService

توقيع الفواتير بالتوقيع الرقمي وتوليد رموز QR.

**الأساليب:**

- `sign(CSID $csid, string $privateKeyContent, string $canonicalXml, string $invoiceHash): InvoiceSigningResult`

#### QrCodeGeneratorService

توليد رموز QR متوافقة مع ZATCA.

**الأساليب:**

- `generate(CSID $csid, string $invoiceHash, string $canonicalXml, string $signatureValue): string`

#### ProductionCsidGeneratorService

طلب شهادات الإنتاج بعد التحقق من الامتثال، وتجديدها عند الحاجة.

**الأساليب:**

- `requestProductionCertificate(string $binarySecurityToken, string $secret, string $ccsidRequestId): CSID`
- `renewProductionCertificate(string $binarySecurityToken, string $secret, string $otp, string $b64Csr): CSID`

#### InvoiceSubmissionService

إرسال الفواتير إلى ZATCA عبر واجهتي الإبلاغ أو التخليص.

**الأساليب:**

- `submit(CSID $csid, bool $isSimplified, string $invoiceHash, string $invoiceUuid, string $invoiceXml): SubmissionResponse`

#### InvoiceBuilder

بناء ملف فاتورة XML بصيغة UBL 2.1 (غير موقّع) من مصفوفات PHP عادية، مع حساب المجاميع ومجموعات ضريبة القيمة المضافة تلقائيًا.

**الأساليب:**

- `InvoiceBuilder::simplified(array $data): string` - بناء فاتورة مبسطة (B2C)
- `InvoiceBuilder::standard(array $data): string` - بناء فاتورة قياسية (B2B)
- `InvoiceBuilder::build(array $data): string` - بناء أي نوع مدعوم (`invoice` ‏388، `credit` ‏383، `debit` ‏381)

### الكيانات (Entities)

#### CSID

تمثل معرّف التوقيع (شهادة الامتثال أو شهادة الإنتاج).

**الخصائص:**

- `string $certificate` - الشهادة بترميز Base64
- `string $secret` - كلمة سر الشهادة
- `string $requestId` - معرّف الطلب من ZATCA

**الأساليب:**

- `static loadFromJson(string $filepath): self`
- `saveAsJson(string $filepath): void`

#### InvoiceHashingResult

نتيجة عملية تشفير الفاتورة.

**الخصائص:**

- `string $invoiceHash` - بصمة SHA-256 بترميز Base64
- `string $uuid` - المعرف الفريد للفاتورة UUID
- `string $b64Invoice` - الفاتورة بترميز Base64
- `string $b64CanonicalXml` - ملف XML القياسي (Canonical) بترميز Base64

#### InvoiceSigningResult

نتيجة عملية توقيع الفاتورة.

**الخصائص:**

- `string $signature` - التوقيع الرقمي
- `string $b64SignedInvoice` - الفاتورة الموقعة بترميز Base64
- `string $b64QrCode` - رمز QR بترميز Base64

#### SubmissionResponse

الاستجابة من إرسال الفاتورة إلى ZATCA.

**الخصائص:**

- `ValidationResults $validationResults` - رسائل التحقق
- `string $status` - حالة الإرسال (REPORTED/CLEARED)
- `bool $isSubmitted` - هل تم الإرسال بنجاح

#### ValidationResponse

الاستجابة من التحقق من الامتثال.

**الخصائص:**

- `ValidationResults $validationResults` - رسائل التحقق
- `string|null $reportingStatus` - حالة الإبلاغ
- `string|null $clearanceStatus` - حالة التخليص
- `string|null $qrSellerStatus` - حالة QR للبائع
- `string|null $qrBuyerStatus` - حالة QR للمشتري

#### ValidationResults

رسائل التحقق من ZATCA.

**الخصائص:**

- `array $infoMessages` - رسائل معلوماتية
- `array $warningMessages` - رسائل تحذيرية
- `array $errorMessages` - رسائل الأخطاء
- `string $status` - حالة التحقق الإجمالية

## بناء فواتير UBL من المصفوفات

```php
use Smart\Zatca\InvoiceBuilder;

$xml = InvoiceBuilder::simplified([
    'id' => 'SME-0001',
    'uuid' => 'a9ea6c06-1a83-432c-8854-dd023a1753d1',
    'issueDate' => '2026-09-23',
    'issueTime' => '10:00:00',
    'currency' => 'SAR',
    'icv' => 1,          // عدّاد الفاتورة، تسلسلي لكل جهاز فوترة
    'pih' => '...',      // بصمة الفاتورة السابقة (base64)
    'seller' => [
        'name' => 'Test Seller', 'vatNumber' => '310122393500003',
        'crNumber' => '1010010000', 'street' => 'Test St',
        'city' => 'Riyadh', 'postalCode' => '11564', 'countryCode' => 'SA',
    ],
    'lines' => [
        ['name' => 'Item A', 'quantity' => 2, 'unitPrice' => 100.0, 'vatPercent' => 15.0],
    ],
]);

// فواتير B2B التي تتطلب التخليص:
$xml = InvoiceBuilder::standard([...$data, 'buyer' => ['name' => '...', 'vatNumber' => '...', ...]]);
```

يدعم الأنواع `invoice` ‏(388) و`credit` ‏(383) و`debit` ‏(381) عبر `'type' => ...`.
يُخرج المنشئ الفاتورة **غير الموقعة** (بدون UBLExtensions/QR/التوقيع)؛
مرّرها إلى خدمة التشفير، ثم إلى `InvoiceSigningService::sign()`،
التي تُدرج الأجزاء بمعالجة DOM تراعي نطاقات الأسماء.

## تجديد شهادة الإنتاج

```php
$service = (new \Smart\Zatca\Zatca(\Smart\Zatca\Enums\ZatcaEnvironment::PRODUCTION))->production();

$csid = $service->renewProductionCertificate(
    binarySecurityToken: $pcsid->certificate,
    secret: $pcsid->secret,
    otp: 'renewal-otp-from-fatoora-portal',
    b64Csr: $newCsr,
);
```

## الأمثلة

أمثلة عملية كاملة متوفرة في مجلد `examples/`:

1. `0_using_facade.php` - استخدام واجهة Zatca (موصى به)
2. `1_generate_csr.php` - إنشاء CSR والمفتاح الخاص
3. `2_generate_compliance_csid.php` - طلب شهادة الامتثال
4. `3_hash_invoice_xml.php` - تشفير ملف الفاتورة XML
5. `4_generate_qr_code_xml.php` - توليد رمز QR
6. `5_sign_invoice_xml.php` - توقيع الفاتورة
7. `6_check_compliance.php` - التحقق من الامتثال
8. `7_generate_production_csid.php` - طلب شهادة الإنتاج
9. `8_build_ubl_invoice.php` - بناء فاتورة UBL من مصفوفات PHP
10. `9_renew_production_csid.php` - تجديد شهادة الإنتاج

## الاختبارات

تشغيل مجموعة الاختبارات:

```bash
composer test
```

أو باستخدام PHPUnit مباشرة:

```bash
vendor/bin/phpunit
```

## معالجة الأخطاء

تطرح الحزمة استثناءات محددة لسيناريوهات الأخطاء المختلفة:

```php
use Smart\Zatca\Exceptions\ZatcaApiException;
use Smart\Zatca\Exceptions\InvoiceHashingException;
use Smart\Zatca\Exceptions\QrGenerationException;

try {
    // الكود الخاص بك هنا
} catch (ZatcaApiException $e) {
    // معالجة أخطاء API
    echo "API Error: " . $e->getMessage();
} catch (InvoiceHashingException $e) {
    // معالجة أخطاء التشفير
    echo "Hashing Error: " . $e->getMessage();
} catch (QrGenerationException $e) {
    // معالجة أخطاء توليد QR
    echo "QR Error: " . $e->getMessage();
}
```

## أفضل الممارسات

1. **خزّن الشهادات بأمان**: احتفظ بالمفاتيح الخاصة والشهادات في تخزين آمن
2. **استخدم متغيرات البيئة**: خزّن البيانات الحساسة مثل رموز OTP والأسرار في متغيرات البيئة
3. **تحقق قبل الإرسال**: تحقق دائمًا من الامتثال قبل الإرسال إلى الإنتاج
4. **عالج الأخطاء بسلاسة**: طبّق معالجة أخطاء وتسجيلًا (logging) مناسبًا
5. **اختبر في Sandbox**: اختبر تكاملك بدقة في بيئة sandbox
6. **حافظ على تحديث الشهادات**: راقب انتهاء صلاحية الشهادات وجدّدها عند الحاجة

## سير عمل التكامل مع ZATCA

```text
1. إنشاء CSR + المفتاح الخاص
   ↓
2. طلب شهادة الامتثال (مع OTP)
   ↓
3. تشفير ملف الفاتورة XML
   ↓
4. توقيع الفاتورة
   ↓
5. توليد رمز QR
   ↓
6. التحقق من الامتثال
   ↓
7. طلب شهادة الإنتاج
   ↓
8. إرسال الفواتير إلى الإنتاج
```

## المصادر

- [بوابة الفوترة الإلكترونية لهيئة الزكاة والضريبة والجمارك](https://zatca.gov.sa/en/E-Invoicing/Pages/default.aspx)
- [بوابة المطورين ZATCA](https://sandbox.zatca.gov.sa/)
- [الوثائق التقنية للفوترة الإلكترونية](https://zatca.gov.sa/en/E-Invoicing/Introduction/Guidelines/Documents/E-Invoicing_Detailed__Guideline.pdf)

## المساهمة

المساهمات مرحب بها! لا تتردد في تقديم طلب سحب (Pull Request).

## الرخصة

هذه الحزمة برمجيات مفتوحة المصدر مرخصة بموجب [رخصة MIT](LICENSE).

## الشكر والتقدير

طُوّرت وتُصان بواسطة **[فريق Smart](https://smart.sd)** — [eltayeb](https://github.com/Tayeb-Ali) و[CoderX249](https://github.com/CoderX249).

## الدعم

للمشكلات أو الأسئلة أو المساهمات، يرجى زيارة [مستودع GitHub](https://github.com/thesmarter/zatca).

---

**إخلاء المسؤولية**: هذه حزمة غير رسمية ولا تتبع هيئة الزكاة والضريبة والجمارك ولا تحظى بتأييدها. استخدمها على مسؤوليتك الخاصة وتأكد من الامتثال لجميع لوائح ZATCA.
