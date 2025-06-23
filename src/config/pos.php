<?php declare(strict_types=1);

/**
 * pos 配置参数
 */

use think\pos\provider\jlpay\JLLSBPosStrategy;
use think\pos\provider\lipos\LiPosStrategy;
use think\pos\provider\kunpeng\KunPengPosStrategy;

return [
    // pos 品牌服务提供者
    'providers' => [
        // 力 pos
        'lipos' => [
            'class' => LiPosStrategy::class,
            'config' => [
                // 是否开启测试环境模式
                'test' => false,
                'agentNo' => '代理id',
                // 正式网关地址
                'gateway' => '力pos正式网关',
                // 测试网关地址
                'testGateway' => '力pos测试网关',
                // 代理商私钥：签名使用
                'privateKey' => '',
                // 代理商公钥：验签平台响应使用
                'publicKey' => '',
                // pos 平台公钥，加密请求参数用
                'platformPublicKey' => '',
            ],
        ],
        // 移联
        'yilian' => [
            'class' => '\think\pos\provider\yilian\YiLianPosPlatform',
            'config' => [
                'test' => false,
                'gateway' => 'https://extra-business-api.51ydmw.com/',
                'testGateway' => 'https://extra-business-api.ylv3.com/',
                // 代理商编号
                'agentNo' => '119911',
                'aesKey' => '79b13739ff4e4e07',
                'md5Key' => '7d11ffd7850bd7626ac21bb8bdd3dabb',
            ],
        ],
        // 服务商标识
        'lishuaB' => [
            // pos 配置参数
            'config' => [
                // 测试环境
                'test' => false,
                'gateway' => '立刷正式网关',
                // 测试网关地址
                'testGateway' => '立刷测试网关',
                // 立刷机构号
                'agentId' => '立刷机构号',
                // 签名方法，02:RSA私钥签名(SHA256withRSA)
                'signMethod' => '02',
                // 私钥签名
                'privateKey' => '平台私钥',
                // 公钥验签
                'publicKey' => '立刷公钥',
            ],
            'class' => JLLSBPosStrategy::class,
        ],
        // 鲲鹏支付
        'kunpeng' => [
            'class' => KunPengPosStrategy::class,
            'config' => [
                // 是否开启测试环境模式
                'test' => true, // 通常默认为true，在生产中改为false
                // 合作伙伴编号 (文档中是appId)
                'appId' => '91272436', // 测试appId，实际部署时替换
                // 我方RSA私钥 (用于签名请求，解密鲲鹏用我方公钥加密的AES密钥)
                // 需要提供完整的PEM格式私钥字符串，或者只提供密钥内容，由代码格式化
                'privateKey' => 'MIIEvQIBADANBgkqhkiG9w0BAQEFAASCBKcwggSjAgEAAoIBAQDBQRWx7qaaLkuo4rYbISRFpI3fY27AlhDrhMHm2Hhb/I4d0aKaIZNdNN3WCBE2m07XMgYgMjpq/bJIeRzVzxPjWqvLsdaWXDkqgS2IqT9u/O6qF+scjAwlqBhsS9TclsY9tMEojNCrpxKtHLKp6fqmiYd+oyYSHoiQ0H5x790i8gKdzEEkHQ2Cu7aPMkdkbZhiWPaeu6ZsAIRhu0UKUVaaXc4VrF3yOISQc69vZ4Zw7po/AQfzF3Xl/Nj5bE8Rn46yvKAe2O/tUAAYsjbCc1/25Xusyi22SqnO9dyB9Tn/ulwfO/Tr8b5b+9Eqgky48xOvizwmEv2zCAjlwuEB16SFAgMBAAECggEAIPaDh1KQG0tbP2bQLgd0ouZjBp/0s6fFIg8GbeQtf28wJHjt9cFVW/gZAJlmqjxKcd1H+zTmDvrP7pmt5/BG0ahVFkzyr7nyTEQ1apKHzdwZr2yd/0QKRGBELjCvEaMsFDlhGxQNwcGhJ2L2PJI63S4nLNwSMdQAckcF0lRaEUwPbaX9qwO4ZjNuedG3XhyMDrOurLdpwoeWirNpd1eAQXFInli9LtJnxuCHNZa5zFJgl3jXDFeIQHPUksKxgJF1aIYK/9YbzDR04DcVdfo6JaUfa/NoE2Ls2DgWWxhz1taAaN8O0sr3MXvpDldceVWTUFBDaVqZ+QpM5Ssks3fegQKBgQDjW4amP9hPlQJbQMmr2QJg6FPFsfVniJWIT5f6avuYNmHjQLIk6g1mzlp8hu4badF8l6KEaUyoWjK+v0VRu9bgDKMMKeggC5pyPbKqrt9d2XRD+uOCOotrlY2ZchTr70iynkFp8PeSFDLqNHUyzCjnvuERydq5GVSKiTXTGrj+QQKBgQDZmbLe0+KloeEkrnCkQD911KVaUirCV42KcL5OJLNFBBiiRzv7jXB7PthgE+49PGaM2oJI7h6+qSKmI64iat2C5YxtLTHcC+MgHvJdEg/4FlY0RP+wTw42VN4Eia4bcjZjbeoMtQTp9cAqHOSyTe9h7pckFShJdrDIAx8NRT3dRQKBgCF82K9iFgVayFcSiuHh++S0M6qZ1LCkQIosVxFOcrJvyClF3Tdstf6fhFp1MVseUfnNB+YC8ISXjIPl/lrUlQi5M8bV4Vfe/ae4CLn1OfdD0Uk2Cg6jeuekxo+Eayp5Ozb78lydXonIqdsvUNfjlF7WEaaiGbJL1dT18tSeSgNBAoGANmEcvGcDSxVLaJlXeRS9RzsfH5VNLkgnDSPjyy+MxYCij1tx+Al+xK4N8OTKMu93SVgKGyO29zrZd9+O0vcV6HJpR5d10GIAHrTdKLks2Hjsjh94Lp1zFczbtxKZOi6uvOZpCUfrtHQ/08ZouM6VNkoj51aKPOG2iCWPiwd00GkCgYEAoK5sp+NzQ/YDVNJ4t0QQTEzwfujpBNFPSTwLQEsuWff070Fyxgnv7t4MeDcgaSQtqLoXdwZ7Kz9dwMbu1K3OQT9pTdimsidaUMTNMnNa5KDILWarVucosl+Md9s5dkIlzci5bWZQK22bZ7aloBxXdmG9ZZeat+ySQJlzlv6dldE=', // 测试私钥，实际部署时替换
                // 鲲鹏平台RSA公钥 (用于加密AES密钥，验签鲲鹏的响应/通知)
                'kunPengPublicKey' => 'MIIBIjANBgkqhkiG9w0BAQEFAAOCAQ8AMIIBCgKCAQEAwUEVse6mmi5LqOK2GyEkRaSN32NuwJYQ64TB5th4W/yOHdGimiGTXTTd1ggRNptO1zIGIDI6av2ySHkc1c8T41qry7HWllw5KoEtiKk/bvzuqhfrHIwMJagYbEvU3JbGPbTBKIzQq6cSrRyyqen6pomHfqMmEh6IkNB+ce/dIvICncxBJB0Ngru2jzJHZG2YYlj2nrumbACEYbtFClFWml3OFaxd8jiEkHOvb2eGcO6aPwEH8xd15fzY+WxPEZ+OsrygHtjv7VAAGLI2wnNf9uV7rMottkqpzvXcgfU5/7pcHzv06/G+W/vRKoJMuPMTr4s8JhL9swgI5cLhAdekhQIDAQAB', // 测试公钥，实际部署时替换
                // 正式环境请求网关 (鲲pay商户修改和终端类接口文档.2.0.md 第6页未给出生产网关，暂时留空或与测试一致)
                'gateway' => 'https://kpla-api.globebill.com', // 假设的生产网关，需确认
                // 测试环境请求网关
                'testGateway' => 'https://kpla-test.globebill.com',
                // 通知回调地址 (鲲鹏平台会将交易等通知推送到此地址) - 这个通常不在SDK配置，而是在鲲鹏平台设置
                // 'notifyUrl' => 'YOUR_NOTIFY_URL',
            ],
        ]
    ],
];
