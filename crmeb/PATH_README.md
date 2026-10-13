```
├── basic       #基础层
├── exceptions  #异常类型定义
├── interfaces  #接口
├── jobs        #队列任务
├── listens     #定时任务监听
│   └── pay     #支付回调处理逻辑
├── services    #服务层 & 第三方扩展等
│   ├── alipay  #支付宝
│   ├── delivery    #同城配送
│   │   └── storage #同城配送驱动
│   ├── easywechat  #微信相关扩展
│   │   ├── batches         #商家转账到零钱
│   │   ├── broadcast       #小程序直播
│   │   ├── certficates     #v3支付证书相关
│   │   ├── combinePay      #自动分账
│   │   ├── merchant        #自动分账自商户入驻申请等
│   │   ├── miniPayment     #小程序支付
│   │   ├── msgseccheck     #敏感词
│   │   ├── orderShipping   #小程序发货管理
│   │   ├── pay             #v3支付
│   │   ├── subscribe       #小程序订阅消息
│   │   └── wechatTemplate  #公众号模板消息
│   ├── express     #快递查询
│   │   └── storage     
│   ├── printer     #小票打印机
│   │   └── storage
│   ├── product     #商品采集
│   │   └── storage
│   ├── serve       #一号通相关
│   │   └── storage
│   ├── sms         #短信
│   │   └── storage
│   ├── template    #订阅消息/模板消息
│   │   └── storage
│   └── upload      #云存储
│       ├── extend
│       └── storage
├── traits  #公用的traits文件
└── utils   #其他工具包

```
