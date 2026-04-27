<?php

namespace Database\Seeders;

use App\Domains\Search\Models\SearchIndex;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class SearchIndexSeeder extends Seeder
{
    public function run(): void
    {
        DB::table('search_indices')->delete();

        $records = array_merge(
            $this->productRecords(),
            $this->articleRecords(),
            $this->serviceRecords(),
            $this->arabicRecords(),
            $this->overlappingRecords(),
        );

        $now = now()->toDateTimeString();

        foreach ($records as &$record) {
            $record['meta']         = isset($record['meta'])
                ? json_encode($record['meta'], JSON_UNESCAPED_UNICODE)
                : null;
            $record['created_at']   = $now;
            $record['updated_at']   = $now;
            $record['published_at'] = $record['published_at'] ?? $now;
        }

        // Bulk insert بدل loop لأداء أفضل
        foreach (array_chunk($records, 20) as $chunk) {
            DB::table('search_indices')->insert($chunk);
        }

        $total = DB::table('search_indices')->count();
        $this->command->info("SearchIndex seeded: {$total} records.");
        $this->command->table(
            ['Type', 'Count'],
            [
                ['Products (EN)', DB::table('search_indices')->where('data_type_id', 1)->where('language', 'en')->count()],
                ['Articles (EN)', DB::table('search_indices')->where('data_type_id', 2)->where('language', 'en')->count()],
                ['Services (EN)', DB::table('search_indices')->where('data_type_id', 3)->where('language', 'en')->count()],
                ['Arabic',        DB::table('search_indices')->where('language', 'ar')->count()],
            ]
        );
    }

    // ─────────────────────────────────────────────────────────────────
    // data_type_id = 1 → products
    // ─────────────────────────────────────────────────────────────────

    private function productRecords(): array
    {
        return [
            // ─── iphone records (متعددة لاختبار ranking) ─────────────
            [
                'entry_id'     => 1,
                'data_type_id' => 1,
                'project_id'   => 1,
                'language'     => 'en',
                'title'        => 'iPhone 15 Pro Max',
                'content'      => 'The iPhone 15 Pro Max features a titanium design, A17 Pro chip, and a 48MP camera system. Buy now at the best price with free shipping and delivery. Compare prices across stores.',
                'status'       => 'published',
                'meta'         => ['tags' => 'iphone, apple, smartphone, mobile, price', 'brand' => 'Apple'],
            ],
            [
                'entry_id'     => 2,
                'data_type_id' => 1,
                'project_id'   => 1,
                'language'     => 'en',
                'title'        => 'iPhone 14 - Affordable Apple Phone',
                'content'      => 'iPhone 14 offers great value for money. Cheap compared to newer models but still powerful. Purchase online with discount offers and installment plans available.',
                'status'       => 'published',
                'meta'         => ['tags' => 'iphone, apple, cheap, affordable, deal'],
            ],
            [
                'entry_id'     => 3,
                'data_type_id' => 1,
                'project_id'   => 1,
                'language'     => 'en',
                'title'        => 'Samsung Galaxy S24 Ultra - Best Android Phone',
                'content'      => 'Samsung Galaxy S24 Ultra is the best android smartphone with 200MP camera. Shop now and get the best deal with free delivery. Price starts from $1299.',
                'status'       => 'published',
                'meta'         => ['tags' => 'samsung, android, phone, mobile, price'],
            ],
            [
                'entry_id'     => 4,
                'data_type_id' => 1,
                'project_id'   => 1,
                'language'     => 'en',
                'title'        => 'MacBook Pro 16 inch - Apple Laptop',
                'content'      => 'MacBook Pro 16 inch powered by M3 Pro chip. Best laptop for developers and designers. Buy with free shipping, price comparison available. Affordable payment plans.',
                'status'       => 'published',
                'meta'         => ['tags' => 'macbook, apple, laptop, notebook, price'],
            ],
            [
                'entry_id'     => 5,
                'data_type_id' => 1,
                'project_id'   => 1,
                'language'     => 'en',
                'title'        => 'Dell XPS 15 Laptop - Best Price Deal',
                'content'      => 'Dell XPS 15 notebook features Intel Core i9 and NVIDIA GPU. Competitive price with discount offers. Shop now and compare cost with other laptops. Best deal guaranteed.',
                'status'       => 'published',
                'meta'         => ['tags' => 'dell, laptop, notebook, price, deal'],
            ],
            [
                'entry_id'     => 6,
                'data_type_id' => 1,
                'project_id'   => 1,
                'language'     => 'en',
                'title'        => 'Sony WH-1000XM5 Wireless Headphones',
                'content'      => 'Sony WH-1000XM5 headphones offer industry-leading noise cancellation. Buy at best price with free shipping. Compare cost with other headphones before purchase.',
                'status'       => 'published',
                'meta'         => ['tags' => 'sony, headphones, earphone, audio, price'],
            ],
            [
                'entry_id'     => 7,
                'data_type_id' => 1,
                'project_id'   => 1,
                'language'     => 'en',
                'title'        => 'iPad Pro 12.9 inch - Apple Tablet',
                'content'      => 'iPad Pro with M2 chip and Liquid Retina XDR display. Best tablet for creative professionals. Shop now, affordable price with installment offers available.',
                'status'       => 'published',
                'meta'         => ['tags' => 'ipad, apple, tablet, price, buy'],
            ],
            [
                'entry_id'     => 8,
                'data_type_id' => 1,
                'project_id'   => 1,
                'language'     => 'en',
                'title'        => 'Samsung 65 inch 4K Smart TV - Sale',
                'content'      => 'Samsung QLED 4K television with smart features. Purchase at discounted sale price. Free delivery and installation. Compare TV prices and get the best deal today.',
                'status'       => 'published',
                'meta'         => ['tags' => 'samsung, tv, television, price, sale'],
            ],
            [
                'entry_id'     => 9,
                'data_type_id' => 1,
                'project_id'   => 1,
                'language'     => 'en',
                'title'        => 'Google Pixel 8 Pro - Android Smartphone',
                'content'      => 'Google Pixel 8 Pro features the best camera on any android phone. Buy now at competitive price with discount offers. Free shipping on all orders over $50.',
                'status'       => 'published',
                'meta'         => ['tags' => 'google, pixel, android, phone, price'],
            ],
            [
                'entry_id'     => 10,
                'data_type_id' => 1,
                'project_id'   => 1,
                'language'     => 'en',
                'title'        => 'Apple Watch Series 9 - Smart Watch',
                'content'      => 'Apple Watch Series 9 smartwatch with health monitoring and fitness tracking. Shop now at best price. Compare cost with other smartwatches and get the best deal.',
                'status'       => 'published',
                'meta'         => ['tags' => 'apple, watch, smartwatch, wearable, price'],
            ],
        ];
    }

    // ─────────────────────────────────────────────────────────────────
    // data_type_id = 2 → articles
    // ─────────────────────────────────────────────────────────────────

    private function articleRecords(): array
    {
        return [
            // ─── iphone article (يتنافس مع iphone product) ────────────
            [
                'entry_id'     => 11,
                'data_type_id' => 2,
                'project_id'   => 1,
                'language'     => 'en',
                'title'        => 'iPhone 15 Review - Is It Worth Buying?',
                'content'      => 'In this comprehensive review, we analyze the iPhone 15 camera, performance, and battery life. Learn everything you need to know before purchasing. Our guide covers all specs and compares it with competitors.',
                'status'       => 'published',
                'meta'         => ['tags' => 'iphone, review, guide, tutorial', 'category' => 'review'],
            ],
            [
                'entry_id'     => 12,
                'data_type_id' => 2,
                'project_id'   => 1,
                'language'     => 'en',
                'title'        => 'How to Set Up Laravel FULLTEXT Search - Complete Tutorial',
                'content'      => 'This step-by-step tutorial explains how to implement Laravel fulltext search using MySQL MATCH AGAINST. Learn how to index your data, build boolean mode queries, and improve search quality with ranking algorithms.',
                'status'       => 'published',
                'meta'         => ['tags' => 'laravel, php, tutorial, guide, search'],
            ],
            [
                'entry_id'     => 13,
                'data_type_id' => 2,
                'project_id'   => 1,
                'language'     => 'en',
                'title'        => 'Laravel vs Symfony - Developer Guide 2024',
                'content'      => 'A comprehensive guide comparing Laravel and Symfony PHP frameworks. This article explains the differences, use cases, learning curve, and community support. Read to learn which framework is best for your project.',
                'status'       => 'published',
                'meta'         => ['tags' => 'laravel, symfony, php, framework, guide'],
            ],
            [
                'entry_id'     => 14,
                'data_type_id' => 2,
                'project_id'   => 1,
                'language'     => 'en',
                'title'        => 'How to Build a REST API with Laravel - Step by Step',
                'content'      => 'Learn how to build a production-ready REST API using Laravel. This tutorial covers routing, authentication, request validation, and response formatting. Complete guide with examples and best practices.',
                'status'       => 'published',
                'meta'         => ['tags' => 'laravel, api, rest, tutorial, guide'],
            ],
            [
                'entry_id'     => 15,
                'data_type_id' => 2,
                'project_id'   => 1,
                'language'     => 'en',
                'title'        => 'Best PHP Frameworks for Web Development in 2024',
                'content'      => 'An overview of the best PHP frameworks including Laravel, Symfony, and CodeIgniter. This article reviews each framework and explains when to use them. Learn the pros and cons of each option.',
                'status'       => 'published',
                'meta'         => ['tags' => 'php, framework, laravel, tutorial, article'],
            ],
            [
                'entry_id'     => 16,
                'data_type_id' => 2,
                'project_id'   => 1,
                'language'     => 'en',
                'title'        => 'Samsung Galaxy vs iPhone - Which Phone Should You Buy?',
                'content'      => 'A detailed comparison guide between Samsung Galaxy and iPhone smartphones. This review covers camera quality, performance, battery life, and price. Read this tutorial to make the best purchasing decision.',
                'status'       => 'published',
                'meta'         => ['tags' => 'samsung, iphone, comparison, review, guide'],
            ],
            [
                'entry_id'     => 17,
                'data_type_id' => 2,
                'project_id'   => 1,
                'language'     => 'en',
                'title'        => 'Introduction to Machine Learning - Beginner Guide',
                'content'      => 'Learn machine learning from scratch with this comprehensive beginner tutorial. This guide explains supervised learning, neural networks, and how to get started with Python. Step by step examples included.',
                'status'       => 'published',
                'meta'         => ['tags' => 'machine learning, ai, tutorial, guide, python'],
            ],
            [
                'entry_id'     => 18,
                'data_type_id' => 2,
                'project_id'   => 1,
                'language'     => 'en',
                'title'        => 'Docker Tutorial for Laravel Developers',
                'content'      => 'A complete guide to using Docker with Laravel applications. Learn how to containerize your Laravel app, set up MySQL, Redis, and Nginx. This tutorial explains docker-compose configuration step by step.',
                'status'       => 'published',
                'meta'         => ['tags' => 'docker, laravel, tutorial, guide, devops'],
            ],
            [
                'entry_id'     => 19,
                'data_type_id' => 2,
                'project_id'   => 1,
                'language'     => 'en',
                'title'        => 'Understanding MySQL FULLTEXT Search - Technical Guide',
                'content'      => 'Learn how MySQL fulltext search works internally. This article explains BOOLEAN MODE, NATURAL LANGUAGE MODE, scoring algorithms, and index optimization. A technical guide for backend developers.',
                'status'       => 'published',
                'meta'         => ['tags' => 'mysql, fulltext, search, guide, tutorial'],
            ],
            [
                'entry_id'     => 20,
                'data_type_id' => 2,
                'project_id'   => 1,
                'language'     => 'en',
                'title'        => 'Top 10 Laptops for Developers in 2024',
                'content'      => 'A review of the best laptops and notebooks for software developers. This guide covers MacBook Pro, Dell XPS, and ThinkPad. Learn which laptop offers the best performance and value. Complete buying guide.',
                'status'       => 'published',
                'meta'         => ['tags' => 'laptop, notebook, review, guide, developer'],
            ],
        ];
    }

    // ─────────────────────────────────────────────────────────────────
    // data_type_id = 3 → services
    // ─────────────────────────────────────────────────────────────────

    private function serviceRecords(): array
    {
        return [
            // ─── iphone service (يتنافس مع iphone product+article) ───
            [
                'entry_id'     => 21,
                'data_type_id' => 3,
                'project_id'   => 1,
                'language'     => 'en',
                'title'        => 'iPhone Screen Repair Service - Fast Fix',
                'content'      => 'Professional iPhone screen repair and fix service. Book an appointment today and get your phone repaired within 2 hours. We service all iPhone models. Walk-in or schedule a booking online.',
                'status'       => 'published',
                'meta'         => ['tags' => 'iphone, repair, fix, service, booking'],
            ],
            [
                'entry_id'     => 22,
                'data_type_id' => 3,
                'project_id'   => 1,
                'language'     => 'en',
                'title'        => 'Samsung Phone Repair and Maintenance Service',
                'content'      => 'Expert Samsung smartphone repair service including screen replacement, battery fix, and water damage repair. Schedule an appointment online or call us. Fast maintenance service guaranteed.',
                'status'       => 'published',
                'meta'         => ['tags' => 'samsung, phone, repair, maintenance, service'],
            ],
            [
                'entry_id'     => 23,
                'data_type_id' => 3,
                'project_id'   => 1,
                'language'     => 'en',
                'title'        => 'Laptop Repair and Setup Service - Book Now',
                'content'      => 'Professional laptop repair, installation, and setup service for all brands. Book an appointment for hardware repair, software installation, or maintenance. Fast service with warranty.',
                'status'       => 'published',
                'meta'         => ['tags' => 'laptop, repair, setup, installation, booking'],
            ],
            [
                'entry_id'     => 24,
                'data_type_id' => 3,
                'project_id'   => 1,
                'language'     => 'en',
                'title'        => 'TV Installation and Repair Service',
                'content'      => 'Professional TV wall mounting, installation, and repair service. We fix all television brands including Samsung, LG, and Sony. Schedule your appointment for home service visit today.',
                'status'       => 'published',
                'meta'         => ['tags' => 'tv, television, repair, installation, service'],
            ],
            [
                'entry_id'     => 25,
                'data_type_id' => 3,
                'project_id'   => 1,
                'language'     => 'en',
                'title'        => 'Doctor Appointment Booking - Online Consultation',
                'content'      => 'Book a doctor appointment online for medical consultation. Schedule your visit with specialist doctors. Easy online booking system with appointment reminders. Reserve your slot today.',
                'status'       => 'published',
                'meta'         => ['tags' => 'doctor, appointment, booking, consultation, service'],
            ],
            [
                'entry_id'     => 26,
                'data_type_id' => 3,
                'project_id'   => 1,
                'language'     => 'en',
                'title'        => 'Hotel Room Reservation - Book Your Stay',
                'content'      => 'Reserve hotel rooms at the best rates. Book your stay with instant confirmation and free cancellation. Schedule your reservation online. Best hotel booking service with 24/7 support.',
                'status'       => 'published',
                'meta'         => ['tags' => 'hotel, booking, reservation, service, travel'],
            ],
            [
                'entry_id'     => 27,
                'data_type_id' => 3,
                'project_id'   => 1,
                'language'     => 'en',
                'title'        => 'Home Cleaning Service - Book Online',
                'content'      => 'Professional home cleaning and maintenance service. Book an appointment for deep cleaning, regular cleaning, or moving service. Hire experienced cleaners with background check. Schedule today.',
                'status'       => 'published',
                'meta'         => ['tags' => 'cleaning, home, service, booking, hire'],
            ],
            [
                'entry_id'     => 28,
                'data_type_id' => 3,
                'project_id'   => 1,
                'language'     => 'en',
                'title'        => 'Car Repair and Maintenance Service Center',
                'content'      => 'Complete car repair and maintenance service for all vehicle brands. Book an appointment for oil change, tire repair, or engine fix. Fast service with certified mechanics. Schedule a visit today.',
                'status'       => 'published',
                'meta'         => ['tags' => 'car, repair, maintenance, service, booking'],
            ],
        ];
    }

    // ─────────────────────────────────────────────────────────────────
    // Arabic records
    // ─────────────────────────────────────────────────────────────────

    private function arabicRecords(): array
    {
        return [
            // ─── Arabic products ──────────────────────────────────────
            [
                'entry_id'     => 1,
                'data_type_id' => 1,
                'project_id'   => 1,
                'language'     => 'ar',
                'title'        => 'آيفون 15 برو ماكس - أفضل سعر',
                'content'      => 'آيفون 15 برو ماكس يتميز بتصميم التيتانيوم وشريحة A17 Pro وكاميرا 48 ميجابكسل. اشتري الآن بأفضل سعر مع توصيل مجاني. قارن الأسعار واحصل على أفضل عرض. التقسيط متاح.',
                'status'       => 'published',
                'meta'         => ['tags' => 'ايفون، ابل، جوال، سعر، شراء'],
            ],
            [
                'entry_id'     => 3,
                'data_type_id' => 1,
                'project_id'   => 1,
                'language'     => 'ar',
                'title'        => 'سامسونج جالاكسي S24 الترا - أفضل هاتف أندرويد',
                'content'      => 'سامسونج جالاكسي S24 الترا أفضل هاتف ذكي أندرويد بكاميرا 200 ميجابكسل. تسوق الآن واحصل على أفضل سعر مع توصيل مجاني. عروض وخصومات حصرية متاحة.',
                'status'       => 'published',
                'meta'         => ['tags' => 'سامسونج، هاتف، موبايل، سعر، شراء'],
            ],
            [
                'entry_id'     => 4,
                'data_type_id' => 1,
                'project_id'   => 1,
                'language'     => 'ar',
                'title'        => 'ماك بوك برو 16 إنش - لابتوب أبل',
                'content'      => 'ماك بوك برو 16 إنش بمعالج M3 Pro الجديد. أفضل لابتوب للمطورين والمصممين. اشتري الآن بأفضل سعر مع شحن مجاني. تقسيط ميسر متاح.',
                'status'       => 'published',
                'meta'         => ['tags' => 'ماك بوك، ابل، لابتوب، حاسوب، سعر'],
            ],
            // ─── Arabic articles ──────────────────────────────────────
            [
                'entry_id'     => 11,
                'data_type_id' => 2,
                'project_id'   => 1,
                'language'     => 'ar',
                'title'        => 'مراجعة آيفون 15 - هل يستحق الشراء؟',
                'content'      => 'في هذه المراجعة الشاملة نحلل كاميرا آيفون 15 وأدائه وعمر البطارية. تعلم كل ما تحتاج معرفته قبل الشراء. دليلنا يغطي جميع المواصفات ويقارنه مع المنافسين.',
                'status'       => 'published',
                'meta'         => ['tags' => 'ايفون، مراجعة، دليل، شرح'],
            ],
            [
                'entry_id'     => 12,
                'data_type_id' => 2,
                'project_id'   => 1,
                'language'     => 'ar',
                'title'        => 'شرح Laravel كامل للمبتدئين - دليل خطوة بخطوة',
                'content'      => 'تعلم Laravel من الصفر مع هذا الدليل الشامل للمبتدئين. الشرح يغطي المسارات والنماذج وقواعد البيانات. أمثلة عملية وتمارين تطبيقية لمساعدتك على الفهم السريع.',
                'status'       => 'published',
                'meta'         => ['tags' => 'لارافيل، php، شرح، دليل، تعليم'],
            ],
            [
                'entry_id'     => 14,
                'data_type_id' => 2,
                'project_id'   => 1,
                'language'     => 'ar',
                'title'        => 'كيف تبني REST API باستخدام Laravel - شرح كامل',
                'content'      => 'تعلم كيفية بناء REST API احترافي باستخدام Laravel. هذا الدليل يشرح التوجيه والمصادقة والتحقق من الطلبات. شرح تفصيلي مع أمثلة وأفضل الممارسات.',
                'status'       => 'published',
                'meta'         => ['tags' => 'لارافيل، api، شرح، دليل، تعليم'],
            ],
            // ─── Arabic services ──────────────────────────────────────
            [
                'entry_id'     => 21,
                'data_type_id' => 3,
                'project_id'   => 1,
                'language'     => 'ar',
                'title'        => 'خدمة إصلاح شاشة آيفون - إصلاح سريع',
                'content'      => 'خدمة إصلاح وصيانة شاشة آيفون الاحترافية. احجز موعدك اليوم واحصل على إصلاح هاتفك خلال ساعتين. نخدم جميع موديلات آيفون. حجز أونلاين متاح.',
                'status'       => 'published',
                'meta'         => ['tags' => 'ايفون، إصلاح، صيانة، خدمة، حجز'],
            ],
            [
                'entry_id'     => 22,
                'data_type_id' => 3,
                'project_id'   => 1,
                'language'     => 'ar',
                'title'        => 'خدمة إصلاح وصيانة هواتف سامسونج',
                'content'      => 'خدمة إصلاح هواتف سامسونج الاحترافية تشمل تغيير الشاشة وإصلاح البطارية وأضرار الماء. احجز موعدك أونلاين أو اتصل بنا. خدمة صيانة سريعة مع ضمان.',
                'status'       => 'published',
                'meta'         => ['tags' => 'سامسونج، جوال، إصلاح، صيانة، حجز'],
            ],
            [
                'entry_id'     => 25,
                'data_type_id' => 3,
                'project_id'   => 1,
                'language'     => 'ar',
                'title'        => 'حجز موعد طبيب - استشارة طبية أونلاين',
                'content'      => 'احجز موعد طبيب أونلاين للاستشارة الطبية. جدول زيارتك مع الأطباء المتخصصين. نظام حجز سهل مع تذكيرات المواعيد. احجز مكانك اليوم.',
                'status'       => 'published',
                'meta'         => ['tags' => 'طبيب، موعد، حجز، استشارة، خدمة'],
            ],
        ];
    }

    // ─────────────────────────────────────────────────────────────────
    // Overlapping records - نفس الكلمات في أنواع مختلفة
    // ─────────────────────────────────────────────────────────────────

    private function overlappingRecords(): array
    {
        return [
            // ─── "phone repair price" يظهر في الثلاثة ────────────────
            [
                'entry_id'     => 29,
                'data_type_id' => 1,
                'project_id'   => 1,
                'language'     => 'en',
                'title'        => 'Refurbished iPhone - Cheap Price After Repair',
                'content'      => 'Buy refurbished iPhone at cheap price. Each phone has been professionally repaired and tested. Great deal for budget buyers. Compare price with new models and save money.',
                'status'       => 'published',
                'meta'         => ['tags' => 'iphone, refurbished, repair, cheap, price'],
            ],
            [
                'entry_id'     => 30,
                'data_type_id' => 2,
                'project_id'   => 1,
                'language'     => 'en',
                'title'        => 'How to Estimate Phone Repair Cost - Guide',
                'content'      => 'Learn how to estimate the price and cost of phone repair. This guide explains what affects repair prices, when to repair vs buy new, and how to find affordable repair services. Complete tutorial.',
                'status'       => 'published',
                'meta'         => ['tags' => 'phone, repair, price, cost, guide'],
            ],
            // ─── "laptop" في product و service و article ──────────────
            [
                'entry_id'     => 31,
                'data_type_id' => 3,
                'project_id'   => 1,
                'language'     => 'en',
                'title'        => 'MacBook Repair Service - Apple Laptop Fix',
                'content'      => 'Professional MacBook and Apple laptop repair service. Fix screen, keyboard, battery, and logic board issues. Book appointment for diagnosis. Fast repair with genuine parts and warranty.',
                'status'       => 'published',
                'meta'         => ['tags' => 'macbook, apple, laptop, repair, service'],
            ],
            // ─── draft records (لا يجب أن تظهر في البحث) ─────────────
            [
                'entry_id'     => 32,
                'data_type_id' => 1,
                'project_id'   => 1,
                'language'     => 'en',
                'title'        => 'DRAFT - Upcoming iPhone 16 Leak',
                'content'      => 'This is a draft article about upcoming iPhone 16 leaks and rumors. Not published yet.',
                'status'       => 'draft',
                'meta'         => ['tags' => 'iphone, draft, upcoming'],
            ],
            // ─── archived record ──────────────────────────────────────
            [
                'entry_id'     => 33,
                'data_type_id' => 2,
                'project_id'   => 1,
                'language'     => 'en',
                'title'        => 'iPhone 12 Review - Archived',
                'content'      => 'This is an archived review of iPhone 12. The information may be outdated.',
                'status'       => 'archived',
                'meta'         => ['tags' => 'iphone, archived, review'],
            ],
            // ─── weak content (لاختبار fallback) ──────────────────────
            [
                'entry_id'     => 34,
                'data_type_id' => 1,
                'project_id'   => 1,
                'language'     => 'en',
                'title'        => 'Wireless Earbuds',
                'content'      => 'Quality wireless earbuds available in stock.',
                'status'       => 'published',
                'meta'         => null,
            ],
            // ─── high frequency record (لاختبار frequency boost) ──────
            [
                'entry_id'     => 35,
                'data_type_id' => 2,
                'project_id'   => 1,
                'language'     => 'en',
                'title'        => 'Laravel Laravel Laravel - The Ultimate Laravel Guide',
                'content'      => 'Laravel is the best PHP framework. Laravel provides elegant syntax. Laravel makes development fun. Laravel has a great community. Laravel documentation is excellent. Laravel is used worldwide. Learn Laravel today with our complete Laravel tutorial.',
                'status'       => 'published',
                'meta'         => ['tags' => 'laravel, php, framework, tutorial'],
            ],
        ];
    }
}