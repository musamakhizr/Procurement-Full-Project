import { createContext, useContext, useState, ReactNode } from 'react';

type Language = 'en' | 'zh';

interface LanguageContextType {
  language: Language;
  setLanguage: (lang: Language) => void;
  t: (key: string) => string;
}

interface Translations {
  [key: string]: string;
}

const translations: Record<Language, Translations> = {
  en: {
    // Header
    'header.marketplace': 'Marketplace',
    'header.aiAssistant': 'AI Assistant',
    'header.sourcing': 'Sourcing Service',
    'header.howItWorks': 'How It Works',
    'header.signIn': 'Sign In',
    'header.getStarted': 'Get Started',
    'header.requestDemo': 'Request Demo',
    'header.admin': 'Admin',
    'header.procurementList': 'Procurement List',
    'header.account': 'Account',
    'header.register': 'Register',
    'header.dashboard': 'Dashboard',
    'header.signOut': 'Sign Out',
    'header.catalog': 'Catalog',
    'header.myList': 'My List',
    'header.requests': 'Requests',
    
    // Dashboard / Workspace Home
    'dashboard.welcome': 'Welcome back',
    'dashboard.welcomeSubtitle': 'Start your procurement workflow or review your pending requests',
    'dashboard.pending': 'Pending',
    'dashboard.active': 'Active',
    'dashboard.pendingRequests': 'Pending Requests',
    'dashboard.activeOrders': 'Active Orders',
    'dashboard.monthSpend': 'This Month Spend',
    'dashboard.savings': 'Average Savings',
    'dashboard.startProcurement': 'Start Procurement',
    'dashboard.startProcurementDesc': 'Choose your workflow and begin your procurement request',
    'dashboard.quotesWaiting': 'Quotes Waiting',
    'dashboard.actionRequired': 'Action Required',
    'dashboard.inProgress': 'In Progress',
    'dashboard.trackAll': 'Track All',
    'dashboard.deliveriesThisWeek': 'Deliveries This Week',
    'dashboard.viewSchedule': 'View Schedule',
    'dashboard.actionCenter': 'Action Center',
    'dashboard.actionCenterDesc': 'Urgent tasks and pending actions requiring your attention',
    'dashboard.modeSearch': 'Search Products',
    'dashboard.modeAI': 'Ask AI',
    'dashboard.modeSourcing': 'Submit Request',
    'dashboard.modeLinks': 'Product Links',
    'dashboard.searchPlaceholder': 'Search products, categories, or SKU...',
    'dashboard.aiPlaceholder': 'Describe what you need for your organization...',
    'dashboard.sourcingPlaceholder': 'Tell us what you need, quantity, specifications, and timeline...',
    'dashboard.linksPlaceholder': 'Paste product links from any supplier website...',
    'dashboard.searchButton': 'Search Catalog',
    'dashboard.aiButton': 'Get AI Recommendations',
    'dashboard.sourcingButton': 'Submit Sourcing Request',
    'dashboard.linksButton': 'Submit Product Links',
    'dashboard.browseCatalog': 'Browse Catalog',
    'dashboard.myList': 'My List',
    'dashboard.customRequest': 'Custom Request',
    'dashboard.trackOrders': 'Track Orders',
    'dashboard.pendingRequestsTitle': 'Pending Requests',
    'dashboard.viewAll': 'View All',
    'dashboard.statusPendingQuote': 'Pending Quote',
    'dashboard.statusInReview': 'In Review',
    'dashboard.statusQuoteReceived': 'Quote Received',
    'dashboard.statusNeedsInfo': 'Needs Info',
    'dashboard.statusAwaitingApproval': 'Awaiting Approval',
    'dashboard.nextStep': 'Next step',
    'dashboard.nextStepReview': 'Review quote and compare options',
    'dashboard.nextStepComplete': 'Complete missing information',
    'dashboard.nextStepApprove': 'Review and approve purchase order',
    'dashboard.actionReviewQuote': 'Review Quote',
    'dashboard.actionCompleteDetails': 'Complete Details',
    'dashboard.actionApproveOrder': 'Approve Order',
    'dashboard.urgent': 'URGENT',
    'dashboard.viewAllRequests': 'View All Requests',
    'dashboard.recentActivity': 'Recent Activity',
    'dashboard.quickTemplates': 'Quick Templates',
    'dashboard.quickTemplatesDesc': 'Start with pre-configured procurement workflows',
    'dashboard.useTemplate': 'Use template',
    'dashboard.request1Title': 'Classroom Art Supplies - Bulk Order',
    'dashboard.request2Title': 'Office Furniture - New Campus Wing',
    'dashboard.order1Title': 'Science Lab Equipment Package',
    'dashboard.activity1': 'Quote received for sports equipment request',
    'dashboard.activity2': 'Order #ORD-2024-156 shipped',
    'dashboard.activity3': 'New sourcing request submitted',
    'dashboard.template1': 'Classroom Starter Kit',
    'dashboard.template1Desc': 'Complete supplies for new classroom setup',
    'dashboard.template2': 'Office Setup Package',
    'dashboard.template2Desc': 'Essential furniture and equipment bundle',
    'dashboard.template3': 'Event Supplies Bundle',
    'dashboard.template3Desc': 'Ready-made package for school events',
    'dashboard.browseByCategory': 'Browse by Category',
    'dashboard.categoryOffice': 'Office Supplies',
    'dashboard.categoryClassroom': 'Classroom Materials',
    'dashboard.categoryArt': 'Art & Crafts',
    'dashboard.categorySports': 'Sports Equipment',
    'dashboard.categoryEvents': 'Event Supplies',
    'dashboard.categoryTechnology': 'Technology',
    'dashboard.popularCategories': 'Popular Categories',
    'dashboard.viewAllCategories': 'View All Categories',
    
    // Sign In / Sign Up
    'signIn.error': 'Invalid email or password. Please try again.',
    'signUp.error': 'Registration failed. Please try again.',
    
    // Marketplace / Procurement Catalog
    'marketplace.title': 'Procurement Catalog',
    'marketplace.subtitle': 'Browse verified products with business pricing and procurement support',
    'marketplace.productsInCategory': 'products in category',
    'marketplace.searchPlaceholder': 'Search products or categories...',
    'marketplace.aiPlaceholder': 'Describe what you need for your classroom, event, or office...',
    'marketplace.sourcingPlaceholder': 'Tell us what you need, quantity, specifications, and deadline...',
    'marketplace.linksPlaceholder': 'Paste product links from 1688, Amazon, supplier sites...',
    'marketplace.filters': 'Filters',
    'marketplace.productsAvailable': 'products available',
    'marketplace.sortRelevance': 'Sort: Relevance',
    'marketplace.sortPriceLow': 'Price: Low to High',
    'marketplace.sortPriceHigh': 'Price: High to Low',
    'marketplace.sortLeadTime': 'Lead Time',
    'marketplace.sortMOQ': 'MOQ: Low to High',
    
    // Workflow Modes
    'marketplace.modeSearch': 'Search Products',
    'marketplace.modeAI': 'Ask AI',
    'marketplace.modeSourcing': 'Submit Request',
    'marketplace.modeLinks': 'Product Links',
    
    // Workflow Buttons
    'marketplace.searchButton': 'Search Catalog',
    'marketplace.aiButton': 'Get Recommendations',
    'marketplace.sourcingButton': 'Submit Request',
    'marketplace.linksButton': 'Submit Links',
    
    // Filters
    'marketplace.filterCategory': 'Category',
    'marketplace.filterMOQ': 'MOQ',
    'marketplace.filterLeadTime': 'Lead Time',
    'marketplace.filterOptions': 'Options',
    'marketplace.filterAllCategories': 'All Categories',
    'marketplace.filterAny': 'Any',
    'marketplace.filterVerifiedOnly': 'Verified Only',
    'marketplace.filterCustomizable': 'Customizable',
    
    // Product Card
    'marketplace.verified': 'ProcurePro Verified',
    'marketplace.customizable': 'Customizable',
    'marketplace.moq': 'MOQ:',
    'marketplace.leadTime': 'Lead Time:',
    'marketplace.units': 'units',
    'marketplace.businessPricing': 'Business Pricing',
    'marketplace.addToList': 'Add to List',
    'marketplace.addedToList': 'Added to List',
    'marketplace.quote': 'Quote',
    
    // Categories
    'marketplace.categoryAll': 'All Products',
    'marketplace.categoryOfficeSupplies': 'Office Supplies',
    'marketplace.categoryClassroom': 'Classroom Materials',
    'marketplace.categoryArtsCrafts': 'Art & Crafts',
    'marketplace.categoryTechnology': 'Technology',
    'marketplace.categoryFurniture': 'Furniture',
    'marketplace.categorySports': 'Sports Equipment',
    'marketplace.categoryEvents': 'Event Supplies',
    'marketplace.categoryCatering': 'Catering',
    
    // Subcategories - Office Supplies
    'marketplace.subCategoryWriting': 'Writing Instruments',
    'marketplace.subCategoryPaper': 'Paper Products',
    'marketplace.subCategoryOrganization': 'Organization',
    'marketplace.subCategoryDeskAccessories': 'Desk Accessories',
    
    // Subcategories - Classroom
    'marketplace.subCategoryTeachingAids': 'Teaching Aids',
    'marketplace.subCategoryStudentSupplies': 'Student Supplies',
    'marketplace.subCategoryBoardsDisplays': 'Boards & Displays',
    
    // Subcategories - Arts & Crafts
    'marketplace.subCategoryPaints': 'Paints & Brushes',
    'marketplace.subCategoryDrawing': 'Drawing Supplies',
    'marketplace.subCategoryCraftSupplies': 'Craft Supplies',
    
    // Subcategories - Sports
    'marketplace.subCategoryBalls': 'Balls & Equipment',
    'marketplace.subCategoryFitness': 'Fitness Equipment',
    'marketplace.subCategoryOutdoor': 'Outdoor Gear',
    
    // Subcategories - Events
    'marketplace.subCategoryDecorations': 'Decorations',
    'marketplace.subCategoryTableware': 'Tableware',
    'marketplace.subCategoryPartySupplies': 'Party Supplies',
    
    // Subcategories - Technology
    'marketplace.subCategoryComputers': 'Computers & Tablets',
    'marketplace.subCategoryAccessories': 'Accessories',
    'marketplace.subCategoryAudioVisual': 'Audio Visual',
    
    // Subcategories - Furniture
    'marketplace.subCategorySeating': 'Seating',
    'marketplace.subCategoryDesksTables': 'Desks & Tables',
    'marketplace.subCategoryStorage': 'Storage Solutions',
    
    // Subcategories - Catering
    'marketplace.subCategoryDisposables': 'Disposables',
    'marketplace.subCategoryServingEquipment': 'Serving Equipment',
    
    // Support Banner
    'marketplace.supportTitle': 'Can\'t find what you need?',
    'marketplace.supportSubtitle': 'Our procurement team can source custom products and handle special requests for your organization.',
    'marketplace.customSourcingBtn': 'Custom Sourcing Request',
    'marketplace.contactTeamBtn': 'Contact Procurement Team',
    
    // Hero Section
    'hero.title': 'What would you like to procure today?',
    'hero.subtitle': 'Search ready-to-order products, get AI recommendations, submit sourcing requests, or send product links for our team to handle procurement.',
    'hero.searchPlaceholder': 'Search for products, suppliers, or categories...',
    'hero.aiPlaceholder': 'Describe your classroom, event, office, or procurement need...',
    'hero.sourcingPlaceholder': 'Tell us what you need, quantity, specifications, and deadline...',
    'hero.linksPlaceholder': 'Paste one or more product links from 1688, Amazon, supplier sites, etc...',
    'hero.searchMarketplace': 'Search Products',
    'hero.getRecommendations': 'Get AI Recommendations',
    'hero.submitRequest': 'Submit Sourcing Request',
    'hero.submitLinks': 'Submit Product Links',
    
    // Tabs
    'tabs.search': 'Search Marketplace',
    'tabs.ai': 'AI Recommendations',
    'tabs.sourcing': 'Custom Sourcing',
    'tabs.links': 'Submit Product Links',
    
    // Entry Cards
    'entry.search.desc': 'Browse ready-to-order products from verified suppliers.',
    'entry.ai.desc': 'Get smart suggestions based on your scenario, budget, and needs.',
    'entry.sourcing.desc': 'Describe your requirements and our team will source the right options.',
    'entry.links.desc': 'Already found products online? Send us the links and we\'ll handle procurement.',
    
    // Categories
    'categories.browseTitle': 'Browse by Category',
    'categories.description': 'Explore curated products from verified suppliers',
    'categories.viewAll': 'View All Categories',
    'categories.products': 'products',
    
    'category.officeSupplies': 'Office Supplies',
    'category.classroom': 'Classroom Materials',
    'category.artsCrafts': 'Art & Crafts',
    'category.sports': 'Sports Equipment',
    'category.technology': 'Technology',
    'category.furniture': 'Furniture',
    'category.events': 'Event Supplies',
    'category.catering': 'Catering',
    
    // Featured Products
    'featured.title': 'Featured Products',
    'featured.subtitle': 'Curated selection from verified suppliers',
    'featured.note': 'Preview catalog · Full access available after sign in',
    'featured.viewCatalog': 'View Full Catalog',
    'featured.verified': 'Verified',
    'featured.trending': 'Trending',
    'featured.moq': 'MOQ',
    'featured.leadTime': 'Lead Time',
    'featured.reviews': 'reviews',
    
    // Sourcing Service
    'sourcing.mainTitle': 'Can\'t find what you need?\nLet our experts handle it.',
    'sourcing.mainSubtitle': 'Submit a sourcing request for custom, complex, or hard-to-find items. Our procurement team will find the best suppliers for you.',
    'sourcing.customTab': 'Describe Your Request',
    'sourcing.linksTab': 'Submit Product Links',
    
    'sourcing.custom.feature1': 'Multi-supplier comparison',
    'sourcing.custom.feature1Desc': 'We evaluate multiple options',
    'sourcing.custom.feature2': 'Expert verification',
    'sourcing.custom.feature2Desc': 'Quality and pricing validated',
    'sourcing.custom.feature3': 'Estimated Budget',
    'sourcing.custom.step1': 'What do you need?',
    'sourcing.custom.step1Desc': 'Describe the items, specifications, quantity, and purpose...',
    'sourcing.custom.button': 'Sign In to Submit Request',
    
    'sourcing.links.title': 'Already have product links?',
    'sourcing.links.subtitle': 'Send us product links from 1688, Amazon, or supplier websites. We\'ll consolidate, quote, purchase, and coordinate delivery for you.',
    'sourcing.links.step1': 'Product Links',
    'sourcing.links.step1Desc': 'Paste product links here (one per line)\nhttps://www.1688.com/product/...\nhttps://www.amazon.com/dp/...\nhttps://supplier.com/product/...',
    'sourcing.links.button': 'Sign In to Submit Links',
    
    // How It Works
    'howItWorks.title': 'How It Works',
    'howItWorks.subtitle': 'A streamlined procurement process designed to save you time and effort',
    
    'howItWorks.step1.title': 'Submit request or product links',
    'howItWorks.step1.desc': 'Describe what you need or paste product links from any supplier website.',
    
    'howItWorks.step2.title': 'We review and source / validate suppliers',
    'howItWorks.step2.desc': 'Our team evaluates options, compares suppliers, and validates quality and pricing.',
    
    'howItWorks.step3.title': 'Receive quotation and options',
    'howItWorks.step3.desc': 'Get detailed quotes with multiple options, supplier info, and recommendations.',
    
    'howItWorks.step4.title': 'Confirm order and track delivery',
    'howItWorks.step4.desc': 'Approve your selection and monitor the entire procurement and delivery process.',
    
    // Why Choose Us
    'whyUs.title': 'Why Choose Us',
    'whyUs.subtitle': 'More than just a marketplace—a complete procurement solution',
    
    'whyUs.verified.title': 'Verified Suppliers',
    'whyUs.verified.desc': 'All suppliers thoroughly vetted for quality and reliability',
    
    'whyUs.pricing.title': 'Transparent Pricing',
    'whyUs.pricing.desc': 'Clear costs with no hidden fees or surprises',
    
    'whyUs.team.title': 'Expert Support Team',
    'whyUs.team.desc': 'Dedicated procurement specialists guide every step',
    
    'whyUs.approval.title': 'Approval Workflows',
    'whyUs.approval.desc': 'Built-in approval processes for team procurement',
    
    'whyUs.tracking.title': 'Order Tracking',
    'whyUs.tracking.desc': 'Real-time visibility into every order status',
    
    'whyUs.secure.title': 'Secure Payments',
    'whyUs.secure.desc': 'Enterprise-grade security for all transactions',
    
    // Footer
    'footer.description': 'Smart procurement platform for schools and enterprises. Find, compare, and purchase from verified suppliers.',
    'footer.platform': 'Platform',
    'footer.support': 'Support',
    'footer.company': 'Company',
    'footer.helpCenter': 'Help Center',
    'footer.contactUs': 'Contact Us',
    'footer.buyerProtection': 'Buyer Protection',
    'footer.termsOfService': 'Terms of Service',
    'footer.aboutUs': 'About Us',
    'footer.careers': 'Careers',
    'footer.blog': 'Blog',
    'footer.pressKit': 'Press Kit',
    'footer.supplierNetwork': 'Supplier Network',
    'footer.copyright': '© 2026 ProcurePro. All rights reserved.',
    
    // Product Detail Page
    'product.home': 'Home',
    'product.catalog': 'Catalog',
    'product.inStock': 'In Stock',
    'product.available': 'available',
    'product.volumePricing': 'Volume Pricing',
    'product.quantity': 'Quantity',
    'product.minimumOrder': 'Minimum order',
    'product.unitPrice': 'Unit price',
    'product.totalEstimate': 'Total estimate',
    'product.contactSupport': 'Contact Support',
    'product.downloadSpec': 'Download Spec Sheet',
    'product.description': 'Product Description',
    'product.specifications': 'Specifications',
    'product.specPackSize': 'Pack Size',
    'product.specInkType': 'Ink Type',
    'product.specColors': 'Colors',
    'product.specTipSize': 'Tip Size',
    'product.specMaterial': 'Material',
    'product.specWarranty': 'Warranty',
    
    // Admin Product Management
    'admin.productManagement': 'Product Management',
    'admin.productManagementDesc': 'Manage catalog products, pricing, inventory, and availability',
    'admin.totalProducts': 'Total Products',
    'admin.activeProducts': 'Active Products',
    'admin.lowStock': 'Low Stock',
    'admin.categories': 'Categories',
    'admin.thisMonth': 'this month',
    'admin.ofTotal': 'of total',
    'admin.needsAttention': 'Needs attention',
    'admin.inCatalog': 'in catalog',
    'admin.searchProducts': 'Search products, SKU, or category...',
    'admin.import': 'Import',
    'admin.export': 'Export',
    'admin.addProduct': 'Add Product',
    'admin.productName': 'Product Name',
    'admin.category': 'Category',
    'admin.stock': 'Stock',
    'admin.priceRange': 'Price Range',
    'admin.status': 'Status',
    'admin.actions': 'Actions',
    'admin.statusActive': 'Active',
    'admin.statusLowStock': 'Low Stock',
    'admin.statusInactive': 'Inactive',
    'admin.showing': 'Showing',
    'admin.of': 'of',
    'admin.products': 'products',
    'admin.previous': 'Previous',
    'admin.next': 'Next',
    'admin.addNewProduct': 'Add New Product',
    'admin.cancel': 'Cancel',
    'admin.create': 'Create',
    
    // Procurement List Page
    'procurementList.title': 'Procurement List',
    'procurementList.subtitle': 'Review and manage your selected items before requesting a quote',
    'procurementList.emptyTitle': 'Your procurement list is empty',
    'procurementList.emptySubtitle': 'Browse our catalog and add items to build your procurement request',
    'procurementList.browseCatalog': 'Browse Catalog',
    'procurementList.addMoreItems': 'Add More Items',
    'procurementList.summary': 'Order Summary',
    'procurementList.totalItems': 'Total Items',
    'procurementList.subtotal': 'Subtotal',
    'procurementList.estimatedTax': 'Estimated Tax',
    'procurementList.shipping': 'Shipping',
    'procurementList.calculatedAtCheckout': 'Calculated at checkout',
    'procurementList.requestQuote': 'Request Quote',
    'procurementList.downloadList': 'Download List',
    'procurementList.quoteNote': 'Our team will review your request and provide a custom quote within 1 business day',
  },
  zh: {
    // Header
    'header.marketplace': '商品市场',
    'header.aiAssistant': 'AI助手',
    'header.sourcing': '定制采购',
    'header.howItWorks': '如何运作',
    'header.signIn': '登录',
    'header.getStarted': '开始使用',
    'header.requestDemo': '申请演示',
    'header.admin': '管理员',
    'header.procurementList': '采购清单',
    'header.account': '账户',
    'header.register': '注册',
    'header.dashboard': '仪表盘',
    'header.signOut': '注销',
    'header.catalog': '目录',
    'header.myList': '我的清单',
    'header.requests': '请求',
    
    // Dashboard / Workspace Home
    'dashboard.welcome': '欢迎回来',
    'dashboard.welcomeSubtitle': '开始您的采购流程或查看待处理的请求',
    'dashboard.pending': '待处理',
    'dashboard.active': '活跃',
    'dashboard.pendingRequests': '待处理请求',
    'dashboard.activeOrders': '活跃订单',
    'dashboard.monthSpend': '本月支出',
    'dashboard.savings': '平均节省',
    'dashboard.startProcurement': '开始采购',
    'dashboard.startProcurementDesc': '选择您的工作流程并开始您的采购请求',
    'dashboard.quotesWaiting': '等待报价',
    'dashboard.actionRequired': '需要操作',
    'dashboard.inProgress': '进行中',
    'dashboard.trackAll': '跟踪所有',
    'dashboard.deliveriesThisWeek': '本周交货',
    'dashboard.viewSchedule': '查看日程',
    'dashboard.actionCenter': '操作中心',
    'dashboard.actionCenterDesc': '需要您注意的紧急任务和待处理操作',
    'dashboard.modeSearch': '搜索产品',
    'dashboard.modeAI': '询问AI',
    'dashboard.modeSourcing': '提交请求',
    'dashboard.modeLinks': '产品链接',
    'dashboard.searchPlaceholder': '搜索产品、分类或SKU...',
    'dashboard.aiPlaceholder': '描述您的组织需求...',
    'dashboard.sourcingPlaceholder': '告诉我们您需要什么、数量、规格和时间表...',
    'dashboard.linksPlaceholder': '粘贴任何供应商网站的产品链接...',
    'dashboard.searchButton': '搜索目录',
    'dashboard.aiButton': '获取AI推荐',
    'dashboard.sourcingButton': '提交采购请求',
    'dashboard.linksButton': '提交产品链接',
    'dashboard.browseCatalog': '浏览目录',
    'dashboard.myList': '我的清单',
    'dashboard.customRequest': '自定义请求',
    'dashboard.trackOrders': '跟踪订单',
    'dashboard.pendingRequestsTitle': '待处理请求',
    'dashboard.viewAll': '查看全部',
    'dashboard.statusPendingQuote': '待报价',
    'dashboard.statusInReview': '审核中',
    'dashboard.statusQuoteReceived': '已收到报价',
    'dashboard.statusNeedsInfo': '需要信息',
    'dashboard.statusAwaitingApproval': '等待审批',
    'dashboard.nextStep': '下一步',
    'dashboard.nextStepReview': '查看报价并比较选项',
    'dashboard.nextStepComplete': '完成缺失信息',
    'dashboard.nextStepApprove': '查看并批准采购订单',
    'dashboard.actionReviewQuote': '查看报价',
    'dashboard.actionCompleteDetails': '完成详细信息',
    'dashboard.actionApproveOrder': '批准订单',
    'dashboard.urgent': '紧急',
    'dashboard.viewAllRequests': '查看所有请求',
    'dashboard.recentActivity': '最近活动',
    'dashboard.quickTemplates': '快速模板',
    'dashboard.quickTemplatesDesc': '从预配置的采购工作流程开始',
    'dashboard.useTemplate': '使用模板',
    'dashboard.request1Title': '教室艺术用品 - 批量订单',
    'dashboard.request2Title': '办公家具 - 新校园翼',
    'dashboard.order1Title': '科学实验室设备套装',
    'dashboard.activity1': '收到体育用品请求的报价',
    'dashboard.activity2': '订单#ORD-2024-156已发货',
    'dashboard.activity3': '提交新的采购请求',
    'dashboard.template1': '教室启动套件',
    'dashboard.template1Desc': '新教室设置的完整用品',
    'dashboard.template2': '办公室设置套餐',
    'dashboard.template2Desc': '必备家具和设备套装',
    'dashboard.template3': '活动用品套装',
    'dashboard.template3Desc': '学校活动的现成套装',
    'dashboard.browseByCategory': '按分类浏览',
    'dashboard.categoryOffice': '办公用品',
    'dashboard.categoryClassroom': '教室用品',
    'dashboard.categoryArt': '艺术与工艺',
    'dashboard.categorySports': '体育用品',
    'dashboard.categoryEvents': '活动用品',
    'dashboard.categoryTechnology': '技术产品',
    'dashboard.popularCategories': '热门分类',
    'dashboard.viewAllCategories': '查看所有分类',
    
    // Sign In / Sign Up
    'signIn.error': '无效的电子邮件或密码。请重试。',
    'signUp.error': '注册失败。请重试。',
    
    // Marketplace / Procurement Catalog
    'marketplace.title': '采购目录',
    'marketplace.subtitle': '浏览带有商业定价和采购支持的经过验证的产品',
    'marketplace.productsInCategory': '种产品在分类中',
    'marketplace.searchPlaceholder': '搜索产品或分类...',
    'marketplace.aiPlaceholder': '描述您需要的教室、活动或办公室...',
    'marketplace.sourcingPlaceholder': '告诉我们您需要什么、数量、规格和截止日期...',
    'marketplace.linksPlaceholder': '粘贴1688、亚马逊或供应商网站的产品链接...',
    'marketplace.filters': '筛选',
    'marketplace.productsAvailable': '种产品可用',
    'marketplace.sortRelevance': '排序：相关性',
    'marketplace.sortPriceLow': '价格：从低到高',
    'marketplace.sortPriceHigh': '价格：从高到低',
    'marketplace.sortLeadTime': '交货时间',
    'marketplace.sortMOQ': '起订量：从低到高',
    
    // Workflow Modes
    'marketplace.modeSearch': '搜索产品',
    'marketplace.modeAI': '询问AI',
    'marketplace.modeSourcing': '提交请求',
    'marketplace.modeLinks': '产品链接',
    
    // Workflow Buttons
    'marketplace.searchButton': '搜索目录',
    'marketplace.aiButton': '获取推荐',
    'marketplace.sourcingButton': '提交请求',
    'marketplace.linksButton': '提交链接',
    
    // Filters
    'marketplace.filterCategory': '分类',
    'marketplace.filterMOQ': '起订量',
    'marketplace.filterLeadTime': '交货时间',
    'marketplace.filterOptions': '选项',
    'marketplace.filterAllCategories': '所有分类',
    'marketplace.filterAny': '任意',
    'marketplace.filterVerifiedOnly': '仅已验证',
    'marketplace.filterCustomizable': '可定制',
    
    // Product Card
    'marketplace.verified': 'ProcurePro认证',
    'marketplace.customizable': '可定制',
    'marketplace.moq': '起订量：',
    'marketplace.leadTime': '交货时间：',
    'marketplace.units': '件',
    'marketplace.businessPricing': '商业定价',
    'marketplace.addToList': '添加到清单',
    'marketplace.addedToList': '已添加到清单',
    'marketplace.quote': '报价',
    
    // Categories
    'marketplace.categoryAll': '所有产品',
    'marketplace.categoryOfficeSupplies': '办公用品',
    'marketplace.categoryClassroom': '教室用品',
    'marketplace.categoryArtsCrafts': '艺术与工艺',
    'marketplace.categoryTechnology': '技术产品',
    'marketplace.categoryFurniture': '家具',
    'marketplace.categorySports': '体育用品',
    'marketplace.categoryEvents': '活动用品',
    'marketplace.categoryCatering': '餐饮服务',
    
    // Subcategories - Office Supplies
    'marketplace.subCategoryWriting': '书写工具',
    'marketplace.subCategoryPaper': '纸制品',
    'marketplace.subCategoryOrganization': '组织用品',
    'marketplace.subCategoryDeskAccessories': '办公桌配件',
    
    // Subcategories - Classroom
    'marketplace.subCategoryTeachingAids': '教学辅助工具',
    'marketplace.subCategoryStudentSupplies': '学生用品',
    'marketplace.subCategoryBoardsDisplays': '板子和展示架',
    
    // Subcategories - Arts & Crafts
    'marketplace.subCategoryPaints': '颜料和画笔',
    'marketplace.subCategoryDrawing': '绘画用品',
    'marketplace.subCategoryCraftSupplies': '工艺用品',
    
    // Subcategories - Sports
    'marketplace.subCategoryBalls': '球和装备',
    'marketplace.subCategoryFitness': '健身器材',
    'marketplace.subCategoryOutdoor': '户外装备',
    
    // Subcategories - Events
    'marketplace.subCategoryDecorations': '装饰品',
    'marketplace.subCategoryTableware': '餐具',
    'marketplace.subCategoryPartySupplies': '派对用品',
    
    // Subcategories - Technology
    'marketplace.subCategoryComputers': '电脑和平板电脑',
    'marketplace.subCategoryAccessories': '配件',
    'marketplace.subCategoryAudioVisual': '音视频设备',
    
    // Subcategories - Furniture
    'marketplace.subCategorySeating': '座椅',
    'marketplace.subCategoryDesksTables': '桌子和书桌',
    'marketplace.subCategoryStorage': '存储解决方案',
    
    // Subcategories - Catering
    'marketplace.subCategoryDisposables': '一次性用品',
    'marketplace.subCategoryServingEquipment': '服务设备',
    
    // Support Banner
    'marketplace.supportTitle': '找不到您需要的？',
    'marketplace.supportSubtitle': '我们的采购团队可以为您的组织寻找定制产品并处理特殊需求。',
    'marketplace.customSourcingBtn': '定制采购需求',
    'marketplace.contactTeamBtn': '联系采购团队',
    
    // Hero Section
    'hero.title': '今天您想采购什么？',
    'hero.subtitle': '搜索即订产品、获取AI推荐、提交采购需求，或发送产品链接让我们的团队处理采购。',
    'hero.searchPlaceholder': '搜索产品、供应商或分类...',
    'hero.aiPlaceholder': '描述您的教室、活动、办公室或采购需求...',
    'hero.sourcingPlaceholder': '告诉我们您需要什么、数量、规格和截止日期...',
    'hero.linksPlaceholder': '粘贴1688、亚马逊、供应商网站等的产品链接...',
    'hero.searchMarketplace': '搜索产品',
    'hero.getRecommendations': '获取AI推荐',
    'hero.submitRequest': '提交采购需求',
    'hero.submitLinks': '提交产品链接',
    
    // Tabs
    'tabs.search': '搜索市场',
    'tabs.ai': 'AI推荐',
    'tabs.sourcing': '定制采购',
    'tabs.links': '提交产品链接',
    
    // Entry Cards
    'entry.search.desc': '浏览经过验证的供应商的即订产品。',
    'entry.ai.desc': '根据您的场景、预算和需求获取智能建议。',
    'entry.sourcing.desc': '描述您的要求，我们的团队将寻找合适的选项。',
    'entry.links.desc': '已经在网上找到产品了？发送链接给我们，我们将处理采购。',
    
    // Categories
    'categories.browseTitle': '按分类浏览',
    'categories.description': '探索经过验证的供应商精选产品',
    'categories.viewAll': '查看所有分类',
    'categories.products': '种产品',
    
    'category.officeSupplies': '办公用品',
    'category.classroom': '教室用品',
    'category.artsCrafts': '艺术与工艺',
    'category.sports': '体育用品',
    'category.technology': '技术产品',
    'category.furniture': '家具',
    'category.events': '活动用品',
    'category.catering': '餐饮服务',
    
    // Featured Products
    'featured.title': '精选产品',
    'featured.subtitle': '经过验证的供应商精选',
    'featured.note': '预览目录 · 登录后可获得完整访问权限',
    'featured.viewCatalog': '查看完整目录',
    'featured.verified': '已验证',
    'featured.trending': '热门',
    'featured.moq': '最低起订量',
    'featured.leadTime': '交货时间',
    'featured.reviews': '条评价',
    
    // Sourcing Service
    'sourcing.mainTitle': '找不到您需要的？\n让我们的专家来处理。',
    'sourcing.mainSubtitle': '为定制、复杂或难以找到的商品提交采购需。我们的采购团队将为您找到最佳供应商。',
    'sourcing.customTab': '描述您的需求',
    'sourcing.linksTab': '提交产品链接',
    
    'sourcing.custom.feature1': '多供应商比较',
    'sourcing.custom.feature1Desc': '我们评估多个选项',
    'sourcing.custom.feature2': '专家验证',
    'sourcing.custom.feature2Desc': '质量和定价经过验证',
    'sourcing.custom.feature3': '预估预算',
    'sourcing.custom.step1': '您需要什么？',
    'sourcing.custom.step1Desc': '描述商品、规格、数量和用途...',
    'sourcing.custom.button': '登录以提交需求',
    
    'sourcing.links.title': '已经有产品链接了？',
    'sourcing.links.subtitle': '向我们发送1688、亚马逊或供应商网站的产品链接。我们将整合、报价、购买并协调交付。',
    'sourcing.links.step1': '产品链接',
    'sourcing.links.step1Desc': '在此粘贴产品链接（每行一个）\nhttps://www.1688.com/product/...\nhttps://www.amazon.com/dp/...\nhttps://supplier.com/product/...',
    'sourcing.links.button': '登录以提交链接',
    
    // How It Works
    'howItWorks.title': '如何运作',
    'howItWorks.subtitle': '为您节省时间和精力的简化采购流程',
    
    'howItWorks.step1.title': '提交需求或产品链接',
    'howItWorks.step1.desc': '描述您需要什么或粘贴任何供应商网站的产品链接。',
    
    'howItWorks.step2.title': '我们审查并寻源/验证供应',
    'howItWorks.step2.desc': '我们的团队评估选项、比较供应商，并验证质量和定价。',
    
    'howItWorks.step3.title': '收到报价和选项',
    'howItWorks.step3.desc': '获取详细报价，包含多个选项、供应商信息和建议。',
    
    'howItWorks.step4.title': '确认订单并跟踪交付',
    'howItWorks.step4.desc': '批准您的选择并监控整个采购和交付过程。',
    
    // Why Choose Us
    'whyUs.title': '为什么选择我们',
    'whyUs.subtitle': '不仅仅是市场——完整的采购解决方案',
    
    'whyUs.verified.title': '经过验证的供应商',
    'whyUs.verified.desc': '所有供应商都经过质量和可靠性的彻底审查',
    
    'whyUs.pricing.title': '透明定价',
    'whyUs.pricing.desc': '清晰的成本，无隐藏费用或意外',
    
    'whyUs.team.title': '专业支持团队',
    'whyUs.team.desc': '专门的采购专家指导每一步',
    
    'whyUs.approval.title': '审批工作流',
    'whyUs.approval.desc': '团队采购的内置审批流程',
    
    'whyUs.tracking.title': '订单跟踪',
    'whyUs.tracking.desc': '实时了解每个订单状态',
    
    'whyUs.secure.title': '安全支付',
    'whyUs.secure.desc': '所有交易的企业级安全',
    
    // Footer
    'footer.description': '学校和企业的智能采购平台。从经过验证的供应商处查找、比较和购买。',
    'footer.platform': '平台',
    'footer.support': '支持',
    'footer.company': '公司',
    'footer.helpCenter': '帮助中心',
    'footer.contactUs': '联系我们',
    'footer.buyerProtection': '买家保护',
    'footer.termsOfService': '服务条款',
    'footer.aboutUs': '关于我们',
    'footer.careers': '招聘',
    'footer.blog': '博客',
    'footer.pressKit': '媒体资料',
    'footer.supplierNetwork': '供应商网络',
    'footer.copyright': '© 2026 ProcurePro. 版权所有。',
    
    // Product Detail Page
    'product.home': '首页',
    'product.catalog': '目录',
    'product.inStock': '有货',
    'product.available': '可用',
    'product.volumePricing': '批量定价',
    'product.quantity': '数量',
    'product.minimumOrder': '最低订购',
    'product.unitPrice': '单价',
    'product.totalEstimate': '总计估价',
    'product.contactSupport': '联系持',
    'product.downloadSpec': '下载规格表',
    'product.description': '产品描述',
    'product.specifications': '规格',
    'product.specPackSize': '包装尺寸',
    'product.specInkType': '墨水类型',
    'product.specColors': '颜色',
    'product.specTipSize': '笔尖尺寸',
    'product.specMaterial': '材料',
    'product.specWarranty': '保修期',
    
    // Admin Product Management
    'admin.productManagement': '产品管理',
    'admin.productManagementDesc': '管理目录产品、定价、库存和可用性',
    'admin.totalProducts': '总产品数',
    'admin.activeProducts': '活跃产品',
    'admin.lowStock': '低库存',
    'admin.categories': '分类',
    'admin.thisMonth': '本月',
    'admin.ofTotal': '占总数',
    'admin.needsAttention': '需要关注',
    'admin.inCatalog': '在目录中',
    'admin.searchProducts': '搜索产品、SKU或分类...',
    'admin.import': '导入',
    'admin.export': '导出',
    'admin.addProduct': '添加产品',
    'admin.productName': '产品名称',
    'admin.category': '分类',
    'admin.stock': '库存',
    'admin.priceRange': '价格区间',
    'admin.status': '状态',
    'admin.actions': '操作',
    'admin.statusActive': '活跃',
    'admin.statusLowStock': '低库存',
    'admin.statusInactive': '停用',
    'admin.showing': '显示',
    'admin.of': '共',
    'admin.products': '个产品',
    'admin.previous': '上一页',
    'admin.next': '下一页',
    'admin.addNewProduct': '添加新产品',
    'admin.cancel': '取消',
    'admin.create': '创建',
    
    // Procurement List Page
    'procurementList.title': '采购清单',
    'procurementList.subtitle': '在请求报价之前查看和管理您的选定项目',
    'procurementList.emptyTitle': '您的采购清单为空',
    'procurementList.emptySubtitle': '浏览我们的目录并添加项目以构建您的采购请求',
    'procurementList.browseCatalog': '浏览目录',
    'procurementList.addMoreItems': '添加更多项目',
    'procurementList.summary': '单摘要',
    'procurementList.totalItems': '总项目数',
    'procurementList.subtotal': '小计',
    'procurementList.estimatedTax': '预计税款',
    'procurementList.shipping': '运费',
    'procurementList.calculatedAtCheckout': '结账时计算',
    'procurementList.requestQuote': '请求报价',
    'procurementList.downloadList': '下载清单',
    'procurementList.quoteNote': '我们的团队将审查您的请求并在1个工作日内提供自定义报价',
  },
};

const LanguageContext = createContext<LanguageContextType | undefined>(undefined);

export function LanguageProvider({ children }: { children: ReactNode }) {
  const [language, setLanguage] = useState<Language>('en');

  const t = (key: string): string => {
    return translations[language][key] || key;
  };

  return (
    <LanguageContext.Provider value={{ language, setLanguage, t }}>
      {children}
    </LanguageContext.Provider>
  );
}

export function useLanguage() {
  const context = useContext(LanguageContext);
  if (context === undefined) {
    throw new Error('useLanguage must be used within a LanguageProvider');
  }
  return context;
}