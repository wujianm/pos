# 鲲pay商户修改和终端类接口文档.2.0

---

## 第 1 页

鲲pay商户修改和终端类接⼝⽂档-V2.1.1

1. 对接说明
合作伙伴对接通知接⼝时，需要提供http接⼝地址，post请求⽅式，公钥信息（如果没有，联系平台帮助⽣成）
对接注意事项：
通知接收后，合作伙伴需要返回值为：OK
1.1 符号约定

符号 请求⽅约束 服务⽅约束
M 必须包含该域 必须校验该域合法性约束
C 如果条件符合必须包含该域 当条件满⾜时，必须校验该域是否存在
O 该域可选 当该域存在时，必须检查其内容的合法性
 必须与先前报⽂中对应域的值相同的域
1.2 通⽤接⼝参数
1.2.1 请求参数

参数名 参数说明
appId 合作伙伴编号
encryptKey 加密后的AES KEY
data 加密后的数据
sign 签名
timestamp 时间戳, 毫秒值, java参考⽅式: System.currentTimeMillis(), 例: 1694420927678
报⽂格式:

---

## 第 2 页

1.2.2 响应参数

参数名 参数说明
appId 合作伙伴编号
encryptKey 加密后的AES KEY(code 返回00后encryptKey有数据)
data 加密后的数据(code 返回00后data有数据)
sign 签名(code 返回00后sign有值)
code 响应码
msg 响应信息
timestamp 时间戳
报⽂格式:

{
"data": "xY/Zy38mtW1Ko9FCqmo/jXG3H1I610IeTBrRYqifCeXqO0JHyNncRLPQrj2MTRKE",
"appId": "91272436",
"sign":
"AssRAYquYwAVbf1TxLfLFnth6BZzxIdtPVzqcNkAh4n5SUQRUD54ndoBxTTafY+BVuA+jKCxbEwHCQqgjdzEci
q5TfZzIXKyYxuaY+T0/d1/yOJvzLh1Bbh2fGZTi/ssXle9ADNdmQH/HqTfYMYwjfiiE+V+Wn32CZv8qvicUEwp7
PyUE30GJzHhXoqR3/jn3/d1ATMeR+iN2j17JrV++5Voiqr9igQP6jz26kuEg9eFSCYvYXDgGeCjC/ypj7aBc+E0
gShvrsmHcQn8ZAh67RZ8BFwoT0ngQS7XKUyj0if0EK2Q8vXPc3B1DDfCON9N8B2xukJpTCtdetEUT0H1YA==",
"encryptKey":
"kgPQMZEXKCwHy6oktAcp1MyPuJwQiUN7YfkhVCaphxiPQncLWCcED5dU8alIokUH2kWGPW8WdaTpBJbvvfKivq
ggov3fZMrC5y1V/0/yfEcWbZByyUwSoc3QI7Gxh+Bj8Z6y1gkJN5UNhpsoDxd1tazgFBAi/bzgtGWdk++drCgmZ
YlIXDNEjLufQFYrtiDNMHPzXRlPGjWgAmJyhCUk7zs7jRQ1I9ujOB4D/Tx2zhPOjSj7hp7NLA/a5ib9A4WPOmoK
01Um6ps/W5KoD6hR085HiJFnEvnjd8GRg4qYffPB8Ajpok9LQuQYUWxa8D/l8Hh2pskVtzTPF/JtTlTLJw==",
"timestamp": "1694420927678"
}

---

## 第 3 页

{
"appId": "91272436",
"code": "00",
"data":
"kEwrh0Aywqu97tqqSF7GBVClilZSFIhhoJsXk582uXus7njAmLPnA22B2EpRGGbydNCYU7AVvD3wgYeMIxECcQ
==",
"encryptKey":
"hX7xBDZo1zLT232vNeeuxfUPnR6Ix43OjVzqWpft+KQCP3rXZTU0p1LdupSWB/v0bcmZXarvRMHttB3mJ1vi7b
whTQPfsgSGZ9XB3u/8S26WKxFaoHQpW4DcDCh++anr2486VCzu1YE0DYsh3ImluSCxZADp8podwMA8G0Z+FYDe4
+W7RfdyzqQ0KYGTTqmPpZrVYfCFAYnDtAZflpt7zvLSu7ZIN5X+2+i4spduqKKOwkDP6b24FbLVvOHspVAV4WZA
H4N6LDEWUh0FUuT6ryt+dlcZNZ0+NekQ/ZKXXSuONDWVHM23+dxBQSPtWib1NXCORByHcOoFdKhuFhgMzQ==",
"msg": "查询成功",
"sign":
"aPrNsp9/lu0CyZFskF31KlQzucuLUhOBjAX7QdrmF+B+YJzOBITJAIwzn07hw7inOZXSRNcp7+19jE9GanlW/v
tvJFa2A+Rb7+q+YnNgmZpQ9jPpvNX6uwim2DEF+mEpdiEKf5t2XDPWNsYJyB4vHJvyFRjyrVQwyqcxGA2+iYhP+
pniXAqrKz3gOu1WLnrNrCfXLfDW1h4sy4xeycXPXOPDBSc0s3058KGToqRWyYamB0kOmNsR5mbI0Va5Foyd3mT8
aWgjsMANQ13IqsDOsOPPx/jSTTEwVhJLQ8i6qwsmYaNwMXPJAw/8hoGTYdGfkpN6NDlX+GHwdyE+rL9fgA==",
"timestamp": "1694422202595"
}

1.3 加解密算法
1.3.1 算法
1. RSA ⾮对称加密(密钥size为2048)

2. AES对称加密,填充模式:PKCS5Padding
public static String decryptByApi(String data, String encryptKey, String privateKey)
{
String decryptRandomKey = null;
try {
RSA rsa = new RSA(StrUtil.cleanBlank(privateKey), null);
decryptRandomKey = rsa.decryptStr(encryptKey, KeyType.PrivateKey);
} catch (Exception e) {
throw new RuntimeException("解密失败");
}
String decryptData = AesUtil.decryptFromBase64(decryptRandomKey, data);
log.info("解密后数据decryptData = {}", decryptData);
return decryptData;
}

---

## 第 4 页

3. 签名算法: SHA256WithRSA

1.3.2 加密规则
1. ⽣成16位随机密钥aeskey
2. ⽤aeskey对数据⽤AES算法进⾏加密（对应字段data）
3. ⽤平台公钥对aeskey⽤RSA算法加密（对应字段encryptKey）
4. 把报⽂数据字段名称按字典升序排序，拼接成字符串，对接⽅⽤私钥使⽤RSA算法加密拼接后的字符串⽣成签
名(对应sign字段)
例： 商户状态查询接⼝

public static String encryptToBase64(String key, String content) {
AES aes = new AES(Mode.ECB, Padding.PKCS5Padding,
key.getBytes(StandardCharsets.UTF_8));
return aes.encryptBase64(content);
}
public static String sign(String content, String privateKey) {
try {
PrivateKey priKey = SignUtil.getPrivateKey(privateKey);
Signature signature = Signature.getInstance("SHA256WithRSA");
signature.initSign(priKey);
signature.update(content.getBytes("UTF-8"));
byte[] signed = signature.sign();
return Base64Encoder.encode(signed);
} catch (Exception e) {
log.error("sign(...) 异常：", e, e.getStackTrace());
}
return null;
}

1、原参数：
{
"comCustomerNo":"QB41692673952602"
}
2、⽣成AES随机密钥:
String aesKey = "ZqUUd5KxLhbYwg9r";
3、使⽤AES KEY对原参数进⾏加密
String data = Aes.encrypt("{\"customerNo\":\"QB41692673952602\"}",aesKey);
4、使⽤RSA私钥对整体参数加签
{
"data": "YtUeqiKA6dUJdsq9OcYn/+9/mtWKyhxjIeJ+qVj3H1BaQK8uT7uolbE45Qgl9Cu0",

---

## 第 5 页

"appId": "91272436",
"encryptKey":
"jifMACBSiLya1eN3M02T8wiFO1Pll+TjPn88D5Sc1bQ2rN6TBIpyF26Fe/qGdDVK0pHEEhChmU4Gd6jGZnN5iN
TEBANrg40p5PW1tKhvXcaBCKC2h5DfYGZGe65HDVVIOQHVLTpKwFA9fo8jFneQDwZoo8psX2cmo/j9bXIMHCs+u
jjofZgqcgXyOGPvGNwlS7mpX4Dgf8edejVLvz2iKv9tmWHKfJIMdTuB0dDRQYyBxmUfwAENLu/cG886YOb50t3Q
nXHyCLL5yZI19JFEC4Tz9VIYRL/Jnb75smmU9RRHS80iXsxHHqkz46lDHa1j1edJpzbMv6OKG54CPJqDLA=="
}
待签名
串:appId=91272436&data=YtUeqiKA6dUJdsq9OcYn/+9/mtWKyhxjIeJ+qVj3H1BaQK8uT7uolbE45Qgl9Cu0&
encryptKey=jifMACBSiLya1eN3M02T8wiFO1Pll+TjPn88D5Sc1bQ2rN6TBIpyF26Fe/qGdDVK0pHEEhChmU4G
d6jGZnN5iNTEBANrg40p5PW1tKhvXcaBCKC2h5DfYGZGe65HDVVIOQHVLTpKwFA9fo8jFneQDwZoo8psX2cmo/j
9bXIMHCs+ujjofZgqcgXyOGPvGNwlS7mpX4Dgf8edejVLvz2iKv9tmWHKfJIMdTuB0dDRQYyBxmUfwAENLu/cG8
86YOb50t3QnXHyCLL5yZI19JFEC4Tz9VIYRL/Jnb75smmU9RRHS80iXsxHHqkz46lDHa1j1edJpzbMv6OKG54CP
JqDLA==
5、最终请求报⽂
{
"data": "YtUeqiKA6dUJdsq9OcYn/+9/mtWKyhxjIeJ+qVj3H1BaQK8uT7uolbE45Qgl9Cu0",
"appId": "91272436",
"sign":
"gUU1VBdmpOpo3v22CBoQHuaLNvRK/Fo7ltzlWGYhqVvRWykahRoEDXOUl1F+orTI5kOBLbN4k/31WKO87Rx2J3
+YwhyqbaKwwW8FocQkaC8yK+sCq+ORt8blofVP/mEDwbOA2muTFnGjXbRBQZMFlBJC1rolUXEVSDT9jXfX9fFt2
akQghljhq2WMfYbDT1Jc4jGEHh58HPYnIyn/AdL+z851oIKDmBoDbfzBRyPFP2nqjGug+jnz7/+MvCIXkf8O/Q0
ddB9n4ZUk7GCY2HhiVT+d1owMCUWdUeFDxeywulcvbwiT+yjHBOV6UCbd2NF4FdcnSt/TFb/VLZTmE/DAQ==",
"encryptKey":
"jifMACBSiLya1eN3M02T8wiFO1Pll+TjPn88D5Sc1bQ2rN6TBIpyF26Fe/qGdDVK0pHEEhChmU4Gd6jGZnN5iN
TEBANrg40p5PW1tKhvXcaBCKC2h5DfYGZGe65HDVVIOQHVLTpKwFA9fo8jFneQDwZoo8psX2cmo/j9bXIMHCs+u
jjofZgqcgXyOGPvGNwlS7mpX4Dgf8edejVLvz2iKv9tmWHKfJIMdTuB0dDRQYyBxmUfwAENLu/cG886YOb50t3Q
nXHyCLL5yZI19JFEC4Tz9VIYRL/Jnb75smmU9RRHS80iXsxHHqkz46lDHa1j1edJpzbMv6OKG54CPJqDLA=="
}

1.3.3 解密规则
1. 先验证签名,签名规则⽤接收到报⽂排除签名sign,按字典升序排序,拼接成字符串,⽤平台提供的公钥使⽤RSA算法
⽣成签名,和接收到的sign做验签
2. 对接平台，对接⽅需要⽤私钥解密出encryptKey，为AES加密key
3. ⽤AES加密key，解密data数据
1.4 平台请求地址
测试环境地址： https://kpla-test.globebill.com

---

## 第 6 页

接⼝类型 地址
商户类接⼝ /gateway/customer/api/customer
代付类接⼝ /gateway/profit/api/payment
对账类接⼝ /gateway/profit/api/reconFile
代理类接口 /gateway/admin/api/agent
设备类接口 /gateway/admin//api/materials Api
1.5 测试环境代理公私钥参数
appId: 91272436
公钥:

私钥:

MIIEvQIBADANBgkqhkiG9w0BAQEFAASCBKcwggSjAgEAAoIBAQDBQRWx7qaaLkuo4rYbISRFpI3fY27AlhDrhMH
m2Hhb/I4d0aKaIZNdNN3WCBE2m07XMgYgMjpq/bJIeRzVzxPjWqvLsdaWXDkqgS2IqT9u/O6qF+scjAwlqBhsS9
TclsY9tMEojNCrpxKtHLKp6fqmiYd+oyYSHoiQ0H5x790i8gKdzEEkHQ2Cu7aPMkdkbZhiWPaeu6ZsAIRhu0UKU
VaaXc4VrF3yOISQc69vZ4Zw7po/AQfzF3Xl/Nj5bE8Rn46yvKAe2O/tUAAYsjbCc1/25Xusyi22SqnO9dyB9Tn/
ulwfO/Tr8b5b+9Eqgky48xOvizwmEv2zCAjlwuEB16SFAgMBAAECggEAIPaDh1KQG0tbP2bQLgd0ouZjBp/0s6f
FIg8GbeQtf28wJHjt9cFVW/gZAJlmqjxKcd1H+zTmDvrP7pmt5/BG0ahVFkzyr7nyTEQ1apKHzdwZr2yd/0QKDG
BELjCvEaMsFDlhGxQNwcGhJ2L2PJI63S4nLNwSMdQAckcF0lRaEUwPbaX9qwO4ZjNuedG3XhyMDrOurLdpwoeWi
rNpd1eAQXFInli9LtJnxuCHNZa5zFJgl3jXDFeIQHPUksKxgJF1aIYK/9YbzDR04DcVdfo6JaUfa/NoE2Ls2DgW
Wxhz1taAaN8O0sr3MXvpDldceVWTUFBDaVqZ+QpM5Ssks3fegQKBgQDjW4amP9hPlQJbQMmr2QJg6FPFsfVniJW
IT5f6avuYNmHjQLIk6g1mzlp8hu4badF8l6KEaUyoWjK+v0VRu9bgDKMMKeggC5pyPbKqrt9d2XRD+uOCOotrlY
2ZchTr70iynkFp8PeSFDLqNHUyzCjnvuERydq5GVSKiTXTGrj+QQKBgQDZmbLe0+KloeEkrnCkQD911KVaUirCV
42KcL5OJLNFBBiiRzv7jXB7PthgE+49PGaM2oJI7h6+qSKmI64iat2C5YxtLTHcC+MgHvJdEg/4FlY0RP+wTw42
VN4Eia4bcjZjbeoMtQTp9cAqHOSyTe9h7pckFShJdrDIAx8NRT3dRQKBgCF82K9iFgVayFcSiuHh++S0M6qZ1LC
kQIosVxFOcrJvyClF3Tdstf6fhFp1MVseUfnNB+YC8ISXjIPl/lrUlQi5M8bV4Vfe/ae4CLn1OfdD0Uk2Cg6jeu
ekxo+Eayp5Ozb78lydXonIqdsvUNfjlF7WEaaiGbJL1dT18tSeSgNBAoGANmEcvGcDSxVLaJlXeRS9RzsfH5VNL
kgnDSPjyy+MxYCij1tx+Al+xK4N8OTKMu93SVgKGyO29zrZd9+O0vcV6HJpR5d10GIAHrTdKLks2Hjsjh94Lp1z
FczbtxKZOi6uvOZpCUfrtHQ/08ZouM6VNkoj51aKPOG2iCWPiwd00GkCgYEAoK5sp+NzQ/YDVNJ4t0QQTEzwfuj
pBNFPSTwLQEsuWff070Fyxgnv7t4MeDcgaSQtqLoXdwZ7Kz9dwMbu1K3OQT9pTdimsidaUMTNMnNa5KDILWarVu
cosl+Md9s5dkIlzci5bWZQK22bZ7aloBxXdmG9ZZeat+ySQJlzlv6dldE=
1.6. 平台参数
测试环境平台公钥：

MIIBIjANBgkqhkiG9w0BAQEFAAOCAQ8AMIIBCgKCAQEAwUEVse6mmi5LqOK2GyEkRaSN32NuwJYQ64TB5th4W/y
OHdGimiGTXTTd1ggRNptO1zIGIDI6av2ySHkc1c8T41qry7HWllw5KoEtiKk/bvzuqhfrHIwMJagYbEvU3JbGPb
TBKIzQq6cSrRyyqen6pomHfqMmEh6IkNB+ce/dIvICncxBJB0Ngru2jzJHZG2YYlj2nrumbACEYbtFClFWml3OF
axd8jiEkHOvb2eGcO6aPwEH8xd15fzY+WxPEZ+OsrygHtjv7VAAGLI2wnNf9uV7rMottkqpzvXcgfU5/7pcHzv0
6/G+W/vRKoJMuPMTr4s8JhL9swgI5cLhAdekhQIDAQAB

---

## 第 7 页

⽣产环境平台公钥：

1.7. 计算公式
合作伙伴收益计算公式：商户费率乘以交易⾦额（向下取整保留两位⼩数）减代理商成本乘以交易⾦额（向上取整
保留2位⼩数），将收益减去税额（税额=收益乘以8%向上取整保留2位⼩数）得到最终收益。
商户⼿续费计算公式：
1. 贷记卡等常规⽀付：交易⾦额乘以费率（向下取整保留4位⼩数），再向上取整保留2位⼩数。
2. 借记卡与云闪付1000+借记卡：交易⾦额乘以费率乘以0.9（向下取整保留4位⼩数），再向上取整保留2位⼩
数。

2. 接⼝
2.2 商户类接⼝
2.2.20 商户修改费率
接⼝地址：/modify/rate
接⼝说明：该接⼝⽤于商户修改费率
2.2.20.1 请求报⽂
以下表格是请求报⽂：

中⽂域名 请求参数 参数类型 请求 说明
物料编号 materialsNo String M

费率信息 rate jsonArray M 费率信息
以下是费率信息(rate)内容：
MIIBIjANBgkqhkiG9w0BAQEFAAOCAQ8AMIIBCgKCAQEAt8HSGGhddxk02NCgEIEG49hZUiOFCqKliSulSnUwiu6
/7TcLWlNoBwuR7BIixHvaPJZwidPKT+EHltTOBahg8K/c1sMd7CNkl3GGjgQJ3I7XCe+wB7bJFQCVcES189RsTj
mzuCL6YLBMeCjlTfZqHXPZqDvYpPiGehcZyU2dJTFGTT36IYoWpaardL2i5MRcobxlqPa2yCsv/bLClMeMy5qRa
dnNMsXB52WJnAXIQgIh1nk29+HWolG7lQGXJZfJBixgWmXj6QiIA9bgAira2atJlU/hLZxBRM/FAOVtgRkB0d6O
e/BjdKx6IoeryEPmeoFZs9heoJ2zzpyKr2PsNwIDAQAB

MIIBIjANBgkqhkiG9w0BAQEFAAOCAQ8AMIIBCgKCAQEAmjpK7RfdmKuddJgNy+E5xmMy/IvI9jMK/ZFc4jlCKBU
UWBnhi3sePB6EFNiNCDCuNYUfFQytjRWzMJy4GvvZmrShs3obSRA0K9GrnFKZ8HukVPjTKUe9VtV3hUWeYv++JP
8urLfqFzhcQ1PPLhdMWAuXZpLosrWtxggYRq5bJwZzCuZy+785S2sgm9NN1crkGM7rjcphBjMrhM8iJL8/2u6f8
o2gw6caB7uVmG+7lmfYv7d7TemoudO4dlT/A6n+0ujmhKj39aOobPoUAjnwcOxkZ1fiZdSqoORIYH8e/Y76Eowv
UC34o04ttN6ryiO70+QNgH3H1ElSqgW4YKuxCQIDAQAB

---

## 第 8 页

中⽂域名 请求参数 参数类
型
请
求 说明
⽀付类型编
码

payTypeViewCode

String

M
⽀付类型编码，具体参考：3.9 ⽀付类型编码
费率值 rateValue String M 百分⽐类型 例：0.003(千3)传0.3
封顶值 cappingValue String M 单位：元

2.2.20.2 响应

中⽂域名 请求参数 参数类型 最⼤⻓度 请求 说明
响应码 code String 32 M 00即为成功
响应信息 msg String 1024 O 响应信息描述
数据 data String 解密后类型为:jsonObject
以下是data解密后内容：

中⽂域名 请求参数 参数类型 请求 说明
审核结果 status String M 审核结果，具体参考3.10 审核结果
审核原因 reason String M 审核原因
2.2.20.3 报⽂示例

2.2.21 商户修改交易附加费
接⼝地址：/modify/orderAddRate
接⼝说明：该接⼝⽤于商户修改交易附加费
2.2.21.1 请求报⽂
以下表格是请求报⽂：

中⽂域名 请求参数 参数类型 请求 说明
物料编号 materialsNo String M
费率信息 rate jsonArray M 费率信息
以下是费率信息(rate)内容：

---

## 第 9 页

中⽂域名 请求参数 参数类型 请求 说明
⽀付类型编码 payTypeViewCode String M ⽀付类型编码
固定值费率 fixedValue BigDecimal M 固定值费率(单位:元),最⼤⽀持2位⼩数

2.2.21.2 响应

中⽂域名 请求参数 参数类型 最⼤⻓度 请求 说明
响应码 code String 32 M 00即为成功
响应信息 msg String 1024 O 响应信息描述
数据 data String
以下是data解密后内容：

中⽂域名 请求参数 参数类型 请求 说明
审核结果 status String M 审核结果，具体参考3.10 审核结果
审核原因 reason String M 审核原因
2.2.21.3 报⽂示例
2.2.22 商户费率查询
接⼝地址：/query/rate
接⼝说明：该接⼝⽤于商户费率查询
2.2.22.1 请求报⽂
以下表格是请求报⽂：

中⽂域名 请求参数 参数类型 请求 说明
商户号 customerNo String M
代理商编号 agentNo String M
2.2.22.2 响应

中⽂域名 请求参数 参数类型 最⼤⻓度 请求 说明
响应码 code String 32 M 00即为成功
响应信息 msg String 1024 O 响应信息描述
数据 data String
以下是data解密后内容：

---

## 第 10 页

中⽂域名 请求参数 参数类型 请求 说明
费率类型 payTypeView
Code
String M
备注 remark String C
费率类型名称 payTypeNam
e
 M
⽀付类型编码 payTypeCode M
产品类型 productType M
显示顺序 viewSort C
费率 rateValue M
笔数费 fixedValue C
封顶值 cappingValue C

2.2.22.3 报⽂示例

2.3 终端类接口

2.3.1 终端变更政策
接⼝地址：/updateMaterialsPolicy
接⼝说明：该接⼝⽤于变更终端政策

2.3.1.1 请求报文
以下表格是请求报⽂：
中⽂域名 请求参数 参数类型 请求 说明
转移方式 migrateType String M 转移方式ORDER:有序
号段 NO_ORDER:无序
编号
代理商编号 agentNo String M
设备编号 materialsNoList List M 设备编号转移方式
ORDER时,开始编号、
结束编号; NO_ORDER
时,为设备号集合
政策编号 policyId Long M
2.3.1.2 响应
中⽂域名 请求参数 参数类型 最⼤⻓度 请求 说明

---

## 第 11 页

响应码 code String 32 M 00即为成功
响应信息 msg String 1024 O 响应信息描述
数据 data String
2.3.2终端绑定解绑
接⼝地址：/materialsOperate
接⼝说明：该接⼝⽤于绑定解绑终端
2.3.2.1请求报文
以下表格是请求报⽂：
中⽂域名 请求参数 参数类型 请求 说明
商户号 customerNo String M
代理商编号 agentNo String M
设备编号 materialsNo String M
操作类型 materialsOperate String M 具体参考3.28 操作
类型
旧设备编号 oldMaterialsNo String C

2.3.2.2响应

中⽂域名 请求参数 参数类型 最⼤⻓度 请求 说明
响应码 code String 32 M 00即为成功
响应信息 msg String 1024 O 响应信息描述
数据 data String

3. 参数说明
3.1 结算类型：settleType
参数值 参数说明
T0 当⽇分批
D0 当⽇秒到
T1 下⼀⼯作⽇
D1 下⼀⾃然⽇

---

## 第 12 页

3.2 卡类型：cardType

---

## 第 13 页

参数值 参数说明
CC 贷记卡
DC 借记卡
ZCC 准贷记卡
ZDC 准借记卡
OTHER 其他
3.3 设备类型：materialsType

参数值 参数说明
QR ⼆维码、码牌
DQ_4G ⾃备机电签
DQ_HD 活动机电签
CT_4G ⾃备机⼤POS
CT_HD 活动机⼤POS
3.4 ⽀付类型：payTypeCode

参数值 参数说明
WECHAT 微信
ALIPAY ⽀付宝
UNIONPAY_DOWN_DC 银联⼆维码 - ⼀千以下-借记卡
UNIONPAY_UP_DC 银联⼆维码 - ⼀千以上-借记卡
UNIONPAY_DOWN_CC 银联⼆维码 - ⼀千以下-贷记卡
UNIONPAY_UP_CC 银联⼆维码 - ⼀千以上-贷记卡
POS_DC POS-借记卡
POS_CC POS-贷记卡
POS_NC_DC 银⾏卡闪付-借记卡
POS_NC_CC 银⾏卡闪付-贷记卡
3.5 通知类型：serviceType

---

## 第 14 页

参数值 参数说明
PAY_ORDER 交易订单
SIM_STOP_ORDER 流量卡⽌付订单
DEPOSIT_STOP_ORDER 押⾦⽌付订单
RISK_NOTIFY ⻛控通知
PAYMENT_ORDER_NOTIFY 代付回调
SETTLE_ORDER_NOTIFY 结算订单
CUSTOMER_AUDIT_NOTIFY 商户状态变更通知
CUSTOMER_ADDRESS_CHANGE_NOTIFY 商户地址变更通知
3.6 激活类型：activationType

参数值 参数说明
FINISHED 达标激活
FINISHED_OVER 超期激活
UN_ACTIVATION_ACHIEVE 未激活后再激活
3.7 图⽚类型：imgType

---

## 第 15 页

参数值 参数说明
LEGAL_PERSON_ID_POSITIVE 法⼈身份证⼈像⾯（注册必填）
LEGAL_PERSON_ID_BACK 法⼈身份证国徽⾯（注册必填）
APPLICANT_WITH_ID 法⼈⼿持身份证（注册65岁以上必填）
SETTLE_CARD_IMG 结算卡照⽚（注册必填）
BUSSINESS_LICENSE 营业执照（企业⼊⽹必填）
PLACE_IMG ⻔店⻔头照（注册必填）
STORE_IMG ⻔店内景照（注册必填）
CASH_SPACE_IMG ⻔店收银台照
BANK_CARD_IMG 信⽤卡照⽚（注册65岁以上必填）
CARDHOLDER_SIGN 持卡⼈签名（注册必填）
OCR_FACE ⼈脸识别照⽚（注册必填）
CARDHOLDER_ID_POSITIVE 持卡⼈身份证⼈像⾯（企业⾮法⼈结算必填）
CARDHOLDER_ID_BACK 持卡⼈身份证国徽⾯（企业⾮法⼈结算必填）
CERTIFICATE_IMG 结算授权书（企业⾮法⼈结算必填）
3.8 ⾏业类别：category
参考商户接⼝附件
3.9 ⽀付类型编码：payTypeViewCode

参数值 参数说明
WECHAT 微信
ALIPAY ⽀付宝
NFC ⼩额优惠（包含：银⾏卡闪付，银联⼆维码⼩额）
POS_DC POS-借记卡（包含：银联⼆维码⼤额借记卡）
POS_CC POS-贷记卡（包含：银联⼆维码⼤额贷记卡）
3.10 商户审核状态：customerStatus

---

## 第 16 页

参数值 参数说明
TRUE 审核通过(商户正常)
SILENT 交易休眠(可以刷⼈脸激活)
FALSE 关停(商户不可⽤)
WAIT_AUDIT 待审核
REJECT 审核拒绝
BIND_SUCCESS 绑定成功(商户⼊⽹成功之后绑定机器成功)
3.11 出款账户类型：accountType

参数值 参数说明
SHARE 分润
ACTIVITY_CASHBACK 活动返现
SIM_CARD 流量卡
3.12 账户状态：status

参数值 参数说明
NORMAL 正常
END_IN ⽌收
EN_OUT ⽌⽀
FREEZE 冻结
3.13 提现结算⽅式：settleType

参数值 参数说明
TO_PUBLIC 对公结算
TO_PUBLIC_UNINCORPORATED 对公-[对私结算]
TO_PRIVATE 对私-[法⼈结算]
TO_PRIVATE_UNINCORPORATED 对私-[⾮法⼈结算]
3.14 提现状态：status

---

## 第 17 页

参数值 参数说明
INIT 待提交
WAIT_AUDIT 待审核
WAIT_PAY 出款中
SUCCESS 成功
FAIL 失败
REJECT 拒绝
3.15 设备操作类型：materialsOperate

参数值 参数说明
UN_BIND 解绑
BINDED 绑定
3.16 意愿核身认证状态：verifyIdentityStatus

参数值 参数说明
YES 是
NO 否
3.17 征信类别：type

参数值 参数说明
03 三要素
04 四要素
3.18 ⽌付类型：stopPayType

参数值 参数说明
MACHINE 机具
SIM 流量卡
3.19 ⽌付状态：status

---

## 第 18 页

参数值 参数说明
SUCCESS 成功
FAIL 失败
PROCESSING 处理中
3.20 结算模式：settleMode

参数值 参数说明
DAY ⽇结
MONTH ⽉结
3.21 对公对私：settleAccountType

参数值 参数说明
TO_PUBLIC 对公结算
TO_PUBLIC_UNINCORPORATED 对公-[对私结算]
TO_PRIVATE 对私-[法⼈结算]
TO_PRIVATE_UNINCORPORATED 对私-[⾮法⼈结算]
3.22 ⻛控类型：riskType

参数值 参数说明
CUSTOMER_FREEZE 商户账户冻结
CUSTOMER_UNFREEZE 商户账户解冻
TRADE_FREEZE 交易冻结
TRADE_UNFREEZE 交易解冻
CHANNEL_CUSTOMER_FREEZE 渠道商户冻结
3.23 交易状态：orderStatus

---

## 第 19 页

参数值 参数说明
PROCESSING 处理中
SUCCESS 成功
FAIL 失败
3.24 ⽂件类型：reconFileType

参数值 参数说明
PAYMENT_ORDER 代付订单
PAY_PROFIT_ORDER 交易收益订单
SIM_PROFIT_ORDER 流量卡收益订单
STOP_ORDER ⽌付订单
PAY_ORDER 交易订单
3.25 产品编码：productCode

参数值 参数说明
WECHAT 微信
ALIPAY ⽀付宝
UNIONPAY_QR 银联⼆维码
3.26 产品状态：status

参数值 参数说明
TRUE 已开通
FALSE 未开通
OPENING 开通中
FAIL 开通失败
3.27 商户类型：customerType

参数值 参数说明
SMALL ⼩微
ENTERPRISE 企业

---

## 第 20 页

3.28 操作类型 materialsOperate

参数值 参数说明
BIND 绑定
UN_BIND 解绑

