```
├── command  #自定义命令行工具
├── common   #服务和model等层级模块
│   ├── dao  #数据库操作层
│   │   ├── article     #文章管理
│   │   ├── community   #社区
│   │   ├── delivery    #同城配送
│   │   ├── openapi     #开放接口
│   │   ├── store       #商城商品等模块
│   │   ├── system      #商城系统配置等模块
│   │   ├── user        #用户模块
│   │   └── wechat      #微信相关
│   ├── middleware  #中间件
│   ├── model  #model层
│   │   ├── article     #文章
│   │   ├── community   #社区
│   │   ├── delivery    #同城配送
│   │   ├── openapi     #开放接口
│   │   ├── store       #商品等模块
│   │   ├── system      #系统配置相关
│   │   ├── user        #用户管理
│   │   └── wechat      #微信相关
│   └── repositories    #服务层
│       ├── article     #文章管理
│       ├── community   #社区
│       ├── delivery    #同城配送
│       ├── openapi     #开放接口
│       ├── store       #商品等
│       ├── system      #系统配置
│       ├── user        #用户
│       └── wechat      #微信相关
├── controller  #控制器层
│   ├── admin      #平台后台模块控制器
│   │   ├── article     #文章
│   │   ├── community   #社区
│   │   ├── delivery    #同城配送
│   │   ├── order       #订单/退款单等
│   │   ├── parameter   #商品参数
│   │   ├── points      #积分商城
│   │   ├── store       #商品及活动商品
│   │   ├── system      #系统配置等
│   │   ├── user        #用户
│   │   └── wechat      #微信相关
│   ├── api         #移动端控制器   
│   │   ├── article     #文章
│   │   ├── community   #社区
│   │   ├── server      #客服
│   │   ├── store       #商品相关
│   │   └── user        #用户
│   ├── merchant    #商户后台模块
│   │   ├── store       #商品相关
│   │   ├── system      #系统配置等
│   │   └── user        #用户
│   ├── openapi     #开放接口模块
│   │   └── store   
│   ├── pc          #PC端专属接口 （只有购买PC端才会有此模块）
│   │   └── store
│   └── service     #客服
├── validate    #验证器
│   ├── admin 
│   ├── api
│   └── merchant
├── view    #view页面层
│   ├── install #安装页面
│   └── mobile  #手机模拟
└── webscoket   #长链接
    └── handler #客服聊天长链接处理，消息等
```
