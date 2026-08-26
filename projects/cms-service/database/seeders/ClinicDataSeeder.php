<?php

namespace Database\Seeders;

use Database\Seeders\Support\SeedContext;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * A healthcare clinic project — the third realistic demo tenant alongside
 * Pulse360 (media) and the e-commerce shop.
 *
 * Replaces the old CoreDataSeeder, whose content was placeholder text
 * ('Core Test Project' at slug 'slug', entries named 'Kajjun' and 'Nawras')
 * and which crashed on a second run because it inserted its users without
 * checking for them first.
 *
 * The data types mirror the clinic template the dashboard already ships in
 * src/data/clinicTemplates.js — doctors, services, health posts, testimonials,
 * FAQs — so the seeded tenant matches a project a real operator would build.
 *
 * Field types are chosen so the search indexer can read them:
 * EntryFieldsExtractor treats `text` as the title and `textarea`/`richtext`
 * as body content. A title stored under the wrong type would index blank.
 */
class ClinicDataSeeder extends Seeder
{
    public const PROJECT_SLUG = 'nour-medical-clinic';

    private SeedContext $ctx;

    private int $projectId;

    private int $ownerId;

    /** @var array<string, int> data type slug => id */
    private array $types = [];

    /** @var array<string, array<string, int>> type slug => [field name => id] */
    private array $fields = [];

    public function __construct()
    {
        $this->ctx = new SeedContext;
    }

    public function run(): void
    {
        DB::transaction(function () {
            $this->setupOwnerAndProject();
            $this->setupTypesAndFields();

            $specialties = $this->seedSpecialties();
            $doctorIds = $this->seedDoctors($specialties);

            $this->seedServices($doctorIds);
            $this->seedHealthPosts();
            $this->seedTestimonials();
            $this->seedFaqs();

            $this->report();
        });
    }

    // ─────────────────────────────────────────────────────────────────────
    // Setup
    // ─────────────────────────────────────────────────────────────────────

    private function setupOwnerAndProject(): void
    {
        // The Auth account, not a local users row — see SeedContext::ownerId().
        $this->ownerId = $this->ctx->ownerId('clinic-owner@hypercore.test');

        $existing = $this->ctx->findProjectId(self::PROJECT_SLUG);

        if ($existing !== null) {
            $this->projectId = $existing;

            return;
        }

        $this->projectId = (int) DB::table('projects')->insertGetId([
            'public_id' => (string) Str::uuid(),
            'slug' => self::PROJECT_SLUG,
            'name' => 'Nour Medical Clinic',
            'owner_id' => $this->ownerId,
            'supported_languages' => json_encode(['en', 'ar']),
            'enabled_modules' => json_encode(['cms', 'booking']),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // The owner is a member of their own project. `project_user.user_id`
        // is an Auth id too, so the same resolved value belongs here.
        DB::table('project_user')->insertOrIgnore([
            'project_id' => $this->projectId,
            'user_id' => $this->ownerId,
        ]);
    }

    /**
     * @return array<string, array{name: string, fields: array<string, string>}>
     */
    private function schema(): array
    {
        return [
            'specialties' => [
                'name' => 'Specialties',
                'fields' => [
                    'name' => 'text',
                    'description' => 'textarea',
                    'icon' => 'text',
                ],
            ],
            'doctors' => [
                'name' => 'Doctors',
                'fields' => [
                    'full_name' => 'text',
                    'title' => 'text',
                    'biography' => 'textarea',
                    'specialty' => 'text',
                    'years_experience' => 'number',
                    'languages' => 'text',
                ],
            ],
            'services' => [
                'name' => 'Services',
                'fields' => [
                    'name' => 'text',
                    'description' => 'textarea',
                    'duration_minutes' => 'number',
                    'price' => 'number',
                    'performed_by' => 'text',
                ],
            ],
            'health-posts' => [
                'name' => 'Health Posts',
                'fields' => [
                    'title' => 'text',
                    'excerpt' => 'textarea',
                    'body' => 'richtext',
                    'author' => 'text',
                    'read_time' => 'number',
                ],
            ],
            'testimonials' => [
                'name' => 'Testimonials',
                'fields' => [
                    'patient_name' => 'text',
                    'quote' => 'textarea',
                    'rating' => 'number',
                ],
            ],
            'faqs' => [
                'name' => 'FAQs',
                'fields' => [
                    'question' => 'text',
                    'answer' => 'textarea',
                ],
            ],
        ];
    }

    private function setupTypesAndFields(): void
    {
        foreach ($this->schema() as $slug => $definition) {

            $typeId = $this->ctx->findDataTypeId($this->projectId, $slug);

            if ($typeId === null) {
                $typeId = (int) DB::table('data_types')->insertGetId([
                    'project_id' => $this->projectId,
                    'name' => $definition['name'],
                    'slug' => $slug,
                    'description' => $definition['name'].' for the clinic site',
                    'is_active' => true,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }

            $this->types[$slug] = $typeId;
            $this->fields[$slug] = [];

            $order = 0;

            foreach ($definition['fields'] as $fieldName => $fieldType) {

                $order++;

                $fieldId = DB::table('data_type_fields')
                    ->where('data_type_id', $typeId)
                    ->where('name', $fieldName)
                    ->value('id');

                if ($fieldId === null) {
                    $fieldId = DB::table('data_type_fields')->insertGetId([
                        'data_type_id' => $typeId,
                        'name' => $fieldName,
                        'type' => $fieldType,
                        'required' => in_array($fieldName, ['name', 'full_name', 'title', 'question'], true),
                        // Bilingual project, so the human-readable fields carry
                        // per-language values.
                        'translatable' => in_array(
                            $fieldType,
                            ['text', 'textarea', 'richtext'],
                            true
                        ),
                        'sort_order' => $order,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                }

                $this->fields[$slug][$fieldName] = (int) $fieldId;
            }
        }
    }

    // ─────────────────────────────────────────────────────────────────────
    // Content
    // ─────────────────────────────────────────────────────────────────────

    /**
     * @return array<string, int> slug => entry id
     */
    private function seedSpecialties(): array
    {
        $rows = [
            ['cardiology', 'Cardiology', 'أمراض القلب', 'Diagnosis and treatment of heart and vascular conditions, including ECG, echocardiography and hypertension management.', 'تشخيص وعلاج أمراض القلب والأوعية الدموية، بما فيها تخطيط القلب وضغط الدم.', 'heart-pulse'],
            ['dermatology', 'Dermatology', 'الأمراض الجلدية', 'Care for skin, hair and nail conditions — acne, eczema, psoriasis and skin cancer screening.', 'العناية بأمراض الجلد والشعر والأظافر مثل حب الشباب والإكزيما والصدفية.', 'bandaid'],
            ['pediatrics', 'Pediatrics', 'طب الأطفال', 'Well-child visits, immunisations, growth monitoring and treatment of childhood illness.', 'زيارات متابعة نمو الأطفال والتطعيمات وعلاج أمراض الطفولة.', 'emoji-smile'],
            ['orthopedics', 'Orthopedics', 'جراحة العظام', 'Assessment and treatment of bone, joint, ligament and sports injuries.', 'تقييم وعلاج إصابات العظام والمفاصل والأربطة والإصابات الرياضية.', 'activity'],
            ['internal-medicine', 'Internal Medicine', 'الطب الباطني', 'Primary adult care, chronic disease management and preventive health screening.', 'الرعاية الأولية للبالغين وإدارة الأمراض المزمنة والفحوصات الوقائية.', 'clipboard-pulse'],
        ];

        $ids = [];

        foreach ($rows as [$slug, $nameEn, $nameAr, $descEn, $descAr, $icon]) {
            $entryId = $this->upsertEntry('specialties', $slug, [
                'name' => ['en' => $nameEn, 'ar' => $nameAr],
                'description' => ['en' => $descEn, 'ar' => $descAr],
                'icon' => ['en' => $icon],
            ]);

            $ids[$slug] = $entryId;
        }

        return $ids;
    }

    /**
     * @param  array<string, int>  $specialties
     * @return array<string, int> slug => entry id
     */
    private function seedDoctors(array $specialties): array
    {
        $rows = [
            ['dr-nour-haddad', 'Dr. Nour Haddad', 'د. نور حداد', 'Consultant Cardiologist', 'استشاري أمراض القلب', 'cardiology', 18, 'Arabic, English, French',
                'Dr. Haddad founded Nour Medical Clinic in 2011 after eleven years at the American University Hospital. She specialises in interventional cardiology and preventive heart care, and has published on early detection of arrhythmia in adults under forty.',
                'أسّست الدكتورة حداد عيادة نور الطبية عام 2011 بعد إحدى عشرة سنة في المستشفى الجامعي. تتخصص في قسطرة القلب والرعاية الوقائية للقلب.'],
            ['dr-samir-fares', 'Dr. Samir Fares', 'د. سمير فارس', 'Consultant Dermatologist', 'استشاري الأمراض الجلدية', 'dermatology', 12, 'Arabic, English',
                'Dr. Fares treats inflammatory skin disease and runs the clinic\'s mole-mapping programme. He trained in Lyon and holds a European Diploma in Dermatology.',
                'يعالج الدكتور فارس الأمراض الجلدية الالتهابية ويشرف على برنامج فحص الشامات في العيادة. تدرّب في ليون ويحمل الدبلوم الأوروبي في الأمراض الجلدية.'],
            ['dr-layla-ibrahim', 'Dr. Layla Ibrahim', 'د. ليلى إبراهيم', 'Specialist Paediatrician', 'أخصائية طب الأطفال', 'pediatrics', 9, 'Arabic, English',
                'Dr. Ibrahim looks after children from birth through adolescence, with a focus on childhood asthma and nutrition. Parents book her Saturday morning well-child clinic months ahead.',
                'تعتني الدكتورة إبراهيم بالأطفال من الولادة حتى المراهقة، مع تركيز على الربو والتغذية عند الأطفال.'],
            ['dr-karim-mansour', 'Dr. Karim Mansour', 'د. كريم منصور', 'Orthopaedic Surgeon', 'جرّاح عظام', 'orthopedics', 15, 'Arabic, English, German',
                'Dr. Mansour handles knee and shoulder arthroscopy and works with three local football clubs on injury rehabilitation. He completed a sports medicine fellowship in Munich.',
                'يقوم الدكتور منصور بمناظير الركبة والكتف ويعمل مع ثلاثة أندية كرة قدم محلية في تأهيل الإصابات.'],
            ['dr-hana-yousef', 'Dr. Hana Yousef', 'د. هناء يوسف', 'Internal Medicine Physician', 'أخصائية الطب الباطني', 'internal-medicine', 7, 'Arabic, English',
                'Dr. Yousef leads the clinic\'s diabetes and hypertension programme, combining medication review with structured lifestyle coaching over six-month cycles.',
                'تقود الدكتورة يوسف برنامج السكري وضغط الدم في العيادة، بدمج مراجعة الأدوية مع إرشاد منظّم لنمط الحياة.'],
        ];

        $ids = [];

        foreach ($rows as [$slug, $nameEn, $nameAr, $titleEn, $titleAr, $specialty, $years, $languages, $bioEn, $bioAr]) {
            $ids[$slug] = $this->upsertEntry('doctors', $slug, [
                'full_name' => ['en' => $nameEn, 'ar' => $nameAr],
                'title' => ['en' => $titleEn, 'ar' => $titleAr],
                'biography' => ['en' => $bioEn, 'ar' => $bioAr],
                // Stored as the specialty slug so the value stays meaningful
                // without needing a relation table for this demo.
                'specialty' => ['en' => $specialty],
                'years_experience' => ['en' => (string) $years],
                'languages' => ['en' => $languages],
            ]);
        }

        unset($specialties);

        return $ids;
    }

    /**
     * @param  array<string, int>  $doctorIds
     */
    private function seedServices(array $doctorIds): void
    {
        $rows = [
            ['general-consultation', 'General Consultation', 'استشارة عامة', 30, 45, 'dr-hana-yousef',
                'A 30-minute appointment covering history, examination and a written plan. Suitable for new symptoms or a second opinion.',
                'موعد مدته 30 دقيقة يشمل التاريخ المرضي والفحص وخطة مكتوبة. مناسب للأعراض الجديدة أو الرأي الثاني.'],
            ['cardiac-screening', 'Cardiac Screening Package', 'باقة فحص القلب', 90, 220, 'dr-nour-haddad',
                'Resting ECG, echocardiogram, lipid panel and blood pressure profile, reviewed the same day with a cardiologist.',
                'تخطيط قلب، إيكو، تحليل دهون، وقياس ضغط الدم، مع مراجعة في نفس اليوم مع طبيب القلب.'],
            ['skin-cancer-screening', 'Full-Body Mole Mapping', 'فحص الشامات الكامل', 45, 130, 'dr-samir-fares',
                'Dermoscopic photography of every pigmented lesion, stored for year-on-year comparison. Recommended annually for fair skin or family history.',
                'تصوير بالمنظار الجلدي لكل بقعة مصطبغة، محفوظ للمقارنة سنة بعد سنة. يُنصح به سنوياً.'],
            ['well-child-visit', 'Well-Child Visit', 'زيارة متابعة الطفل', 30, 55, 'dr-layla-ibrahim',
                'Growth and development check with weight, height and head circumference plotted on WHO charts, plus the next immunisation due.',
                'فحص النمو والتطور مع رسم الوزن والطول ومحيط الرأس على مخططات منظمة الصحة العالمية، والتطعيم القادم.'],
            ['sports-injury-assessment', 'Sports Injury Assessment', 'تقييم الإصابات الرياضية', 45, 110, 'dr-karim-mansour',
                'Targeted joint examination with in-clinic ultrasound where useful, ending in a graded return-to-play timeline.',
                'فحص موجّه للمفصل مع تصوير بالموجات فوق الصوتية عند الحاجة، وينتهي بجدول تدريجي للعودة للعب.'],
            ['diabetes-follow-up', 'Diabetes Follow-Up', 'متابعة السكري', 30, 60, 'dr-hana-yousef',
                'Quarterly review of HbA1c, medication tolerance and foot examination, with dietitian referral when targets are missed.',
                'مراجعة ربع سنوية للسكر التراكمي وتحمّل الأدوية وفحص القدم، مع تحويل لأخصائي تغذية عند الحاجة.'],
        ];

        foreach ($rows as [$slug, $nameEn, $nameAr, $minutes, $price, $doctorSlug, $descEn, $descAr]) {
            $this->upsertEntry('services', $slug, [
                'name' => ['en' => $nameEn, 'ar' => $nameAr],
                'description' => ['en' => $descEn, 'ar' => $descAr],
                'duration_minutes' => ['en' => (string) $minutes],
                'price' => ['en' => (string) $price],
                'performed_by' => ['en' => $doctorSlug],
            ]);
        }

        unset($doctorIds);
    }

    private function seedHealthPosts(): void
    {
        $rows = [
            ['understanding-blood-pressure-numbers', 'Understanding Your Blood Pressure Numbers', 'فهم أرقام ضغط الدم', 'Dr. Nour Haddad', 6,
                'What the two numbers in a blood pressure reading actually measure, and which combinations need a doctor rather than a diet change.',
                'ماذا يقيس الرقمان في قراءة ضغط الدم، وأي تركيبة منهما تحتاج طبيباً لا مجرد تعديل غذائي.',
                'A blood pressure reading is written as one number over another — systolic over diastolic. The top number is the pressure while the heart contracts and pushes blood out; the bottom number is the pressure while the heart relaxes and refills. Both matter, and they do not always move together. A reading of 118/76 is comfortably normal. Anything from 130/80 upward is classified as hypertension under current guidance, and the important detail is that a single high reading is not a diagnosis. Pressure varies with the time of day, caffeine, a full bladder and the stress of being measured at all. What we look for is a pattern across several readings taken at rest, at home, over a fortnight. If your top number sits above 140 while the bottom stays under 80, that isolated systolic pattern is common after sixty and still deserves treatment.',
                'تُكتب قراءة ضغط الدم كرقمين، الانقباضي فوق الانبساطي. الرقم الأعلى هو الضغط أثناء انقباض القلب ودفع الدم، والرقم الأسفل هو الضغط أثناء ارتخاء القلب وإعادة امتلائه. كلاهما مهم ولا يتحركان معاً دائماً. قراءة 118/76 طبيعية تماماً. أي قراءة من 130/80 وأعلى تُصنّف ارتفاعاً في ضغط الدم، والتفصيل المهم أن قراءة واحدة مرتفعة ليست تشخيصاً.'],
            ['when-a-childs-fever-needs-a-doctor', 'When a Child\'s Fever Needs a Doctor', 'متى تحتاج حرارة الطفل طبيباً', 'Dr. Layla Ibrahim', 5,
                'Most fevers in children resolve on their own. These are the specific signs that change the answer.',
                'معظم حالات الحرارة عند الأطفال تُشفى وحدها. هذه هي العلامات التي تغيّر الجواب.',
                'Fever is not an illness, it is a response — the body raising its own thermostat to make itself less hospitable to an infection. The number on the thermometer matters far less than parents expect. A child at 39°C who is drinking, playing between naps and settling normally is usually managing a routine viral infection. A child at 38.2°C who is limp, refusing all fluids, breathing fast or hard to rouse needs to be seen the same day, whatever the number says. Bring them in without waiting if the child is under three months old with any temperature above 38°C, if the fever has run past five days, if there is a rash that does not fade when pressed, if there is a stiff neck or persistent vomiting, or if you simply cannot get them to drink.',
                'الحرارة ليست مرضاً بل استجابة، فالجسم يرفع حرارته ليجعل نفسه أقل ملاءمة للعدوى. الرقم على الميزان أقل أهمية بكثير مما يتوقع الأهل. الطفل بحرارة 39 درجة الذي يشرب ويلعب وينام بشكل طبيعي يتعامل عادةً مع عدوى فيروسية عادية.'],
            ['what-to-expect-from-mole-mapping', 'What to Expect From Mole Mapping', 'ماذا تتوقع من فحص الشامات', 'Dr. Samir Fares', 4,
                'A full-body skin check takes about forty-five minutes and produces a photographic baseline you keep for life.',
                'فحص الجلد الكامل يستغرق نحو 45 دقيقة وينتج سجلاً تصويرياً مرجعياً تحفظه مدى الحياة.',
                'The value of mole mapping is not in the single appointment, it is in the comparison. We photograph every pigmented lesion on the body with a dermatoscope, which magnifies and polarises light to show structures below the surface that the naked eye cannot resolve. Those images are stored against your record. A year later, the question stops being "does this mole look worrying" and becomes "has this mole changed", which is a far more answerable question and the one that actually catches melanoma early. You will be asked to undress to your underwear and the examination is systematic — scalp, behind the ears, between the toes, the soles. Most people leave with nothing to act on and a clear record for next year.',
                'قيمة فحص الشامات ليست في الموعد الواحد بل في المقارنة. نصوّر كل بقعة مصطبغة على الجسم بمنظار جلدي يكبّر ويستقطب الضوء لإظهار تراكيب تحت السطح لا تراها العين المجردة. تُحفظ هذه الصور في ملفك.'],
            ['returning-to-sport-after-a-knee-injury', 'Returning to Sport After a Knee Injury', 'العودة للرياضة بعد إصابة الركبة', 'Dr. Karim Mansour', 7,
                'Pain-free is not the same as ready. The staged criteria we use before clearing an athlete to play.',
                'غياب الألم لا يعني الجهوزية. المعايير المتدرجة التي نستخدمها قبل السماح للرياضي بالعودة.',
                'The most common reason an athlete re-injures a knee is returning on the basis of how it feels rather than what it can do. Swelling settles and pain fades well before the joint regains the strength and control that protect it under load. We work through four stages. First, restore full range of motion and get the swelling down. Second, rebuild quadriceps and hamstring strength until the injured leg reaches at least ninety per cent of the healthy side on a hop test. Third, reintroduce running, then cutting, then contact, each only after the previous step passes without next-day swelling. Fourth, sport-specific drills at match intensity. Skipping the third stage is where most re-tears happen.',
                'أشيع سبب لتكرار إصابة الركبة هو العودة بناءً على الإحساس لا على القدرة. يزول التورّم ويخفّ الألم قبل أن يستعيد المفصل القوة والتحكم اللذين يحميانه تحت الحمل. نعمل على أربع مراحل.'],
            ['reading-your-lab-results-without-panic', 'Reading Your Lab Results Without Panic', 'قراءة نتائج التحاليل بلا قلق', 'Dr. Hana Yousef', 5,
                'Why a value flagged red on the printout is often clinically uninteresting, and which ones are not.',
                'لماذا تكون القيمة المعلّمة بالأحمر غير مهمة سريرياً في الغالب، وأيها ليست كذلك.',
                'Laboratory reference ranges are built so that ninety-five per cent of healthy people fall inside them. That arithmetic guarantees that one healthy person in twenty will sit outside the range on any given test through nothing but normal variation. Run a panel of twenty markers on a perfectly well person and the odds are good that something comes back flagged. This is why we read results as a pattern rather than a list, and against your own previous values rather than the population. A single marginally low haemoglobin in someone who feels well is watched. The same value alongside fatigue, a low ferritin and a rising platelet count is investigated. Bring your old results to the appointment — the trend is worth more than any single number.',
                'تُبنى النطاقات المرجعية للتحاليل بحيث يقع 95% من الأصحاء داخلها. هذه الحسبة تضمن أن شخصاً سليماً من كل عشرين سيقع خارج النطاق في أي تحليل، بسبب التغيّر الطبيعي وحده.'],
            ['preparing-for-your-first-visit', 'Preparing for Your First Visit', 'التحضير لزيارتك الأولى', 'Rania Khoury', 3,
                'What to bring, what to write down beforehand, and how to make a short appointment go further.',
                'ماذا تجلب، وماذا تكتب مسبقاً، وكيف تستفيد أكثر من موعد قصير.',
                'A first consultation is thirty minutes and most of it should be spent on you talking. The preparation that helps most is a written list, in order of what worries you, of the problems you want covered — because the thing patients mention last as they stand up is very often the thing that mattered most. Bring the actual boxes or a photograph of every medication and supplement you take, including the ones you buy without a prescription, and any results from the past two years. If you have a device that logs blood pressure or glucose, bring the readings rather than a summary of them. If the appointment is for a child, bring the vaccination card. And say at the start if you need the consultation in Arabic, English or French, so we can allocate the time properly.',
                'الاستشارة الأولى ثلاثون دقيقة، ومعظمها يجب أن يكون لك للحديث. أكثر تحضير مفيد هو قائمة مكتوبة، مرتبة حسب ما يقلقك، بالمشاكل التي تريد تغطيتها.'],
        ];

        foreach ($rows as [$slug, $titleEn, $titleAr, $author, $readTime, $excerptEn, $excerptAr, $bodyEn, $bodyAr]) {
            $this->upsertEntry('health-posts', $slug, [
                'title' => ['en' => $titleEn, 'ar' => $titleAr],
                'excerpt' => ['en' => $excerptEn, 'ar' => $excerptAr],
                'body' => ['en' => $bodyEn, 'ar' => $bodyAr],
                'author' => ['en' => $author],
                'read_time' => ['en' => (string) $readTime],
            ], Carbon::now()->subDays(random_int(3, 240)));
        }
    }

    private function seedTestimonials(): void
    {
        $rows = [
            ['testimonial-maya-s', 'Maya S.', 'مايا س.', 5,
                'I had put off getting my heart checked for two years. The screening package took one morning and I left with an actual plan instead of vague advice.',
                'أجّلت فحص قلبي سنتين. استغرقت باقة الفحص صباحاً واحداً وخرجت بخطة واضحة بدل نصائح عامة.'],
            ['testimonial-omar-k', 'Omar K.', 'عمر ك.', 5,
                'Dr. Mansour was the first person to explain why my knee kept giving out instead of just telling me to rest. Back playing five-a-side after four months.',
                'الدكتور منصور أول من شرح لي لماذا تخونني ركبتي بدل أن يقول لي ارتح فقط. عدت للعب بعد أربعة أشهر.'],
            ['testimonial-nadia-h', 'Nadia H.', 'نادية ح.', 4,
                'The Saturday paediatric clinic is hard to book but worth the wait. Dr. Ibrahim never makes you feel rushed with a crying toddler.',
                'عيادة الأطفال السبت يصعب الحصول على موعد فيها لكنها تستحق الانتظار. الدكتورة إبراهيم لا تجعلك تشعر بالاستعجال.'],
            ['testimonial-tarek-a', 'Tarek A.', 'طارق ع.', 5,
                'Mole mapping found something on my back I could never have seen myself. Removed the same week and it was caught early.',
                'فحص الشامات وجد شيئاً على ظهري لم أكن لأراه بنفسي. أُزيل في نفس الأسبوع واكتُشف مبكراً.'],
            ['testimonial-lina-m', 'Lina M.', 'لينا م.', 5,
                'Six months in the diabetes programme and my HbA1c is down two points. The follow-up calls are what made the difference.',
                'ستة أشهر في برنامج السكري وانخفض السكر التراكمي نقطتين. مكالمات المتابعة هي التي أحدثت الفرق.'],
        ];

        foreach ($rows as [$slug, $nameEn, $nameAr, $rating, $quoteEn, $quoteAr]) {
            $this->upsertEntry('testimonials', $slug, [
                'patient_name' => ['en' => $nameEn, 'ar' => $nameAr],
                'quote' => ['en' => $quoteEn, 'ar' => $quoteAr],
                'rating' => ['en' => (string) $rating],
            ]);
        }
    }

    private function seedFaqs(): void
    {
        $rows = [
            ['do-i-need-a-referral', 'Do I need a referral to book an appointment?', 'هل أحتاج تحويلاً لحجز موعد؟',
                'No. You can book any consultation directly. A referral is only needed if your insurer requires one for reimbursement — check your policy before the visit.',
                'لا. يمكنك حجز أي استشارة مباشرة. التحويل مطلوب فقط إذا كان تأمينك يشترطه للتعويض، فراجع سياستك قبل الزيارة.'],
            ['which-insurance-do-you-accept', 'Which insurance plans do you accept?', 'أي شركات تأمين تقبلون؟',
                'We bill directly to most regional insurers. Bring your card and ID to the front desk on your first visit so we can verify cover before the consultation starts.',
                'نتعامل مباشرة مع معظم شركات التأمين الإقليمية. أحضر بطاقتك وهويتك في الزيارة الأولى للتحقق من التغطية قبل بدء الاستشارة.'],
            ['how-do-i-get-test-results', 'How do I get my test results?', 'كيف أحصل على نتائج تحاليلي؟',
                'Routine results are ready within two working days and are released through the patient portal. Anything that needs discussing, a doctor calls you about the same day it arrives.',
                'النتائج الروتينية جاهزة خلال يومي عمل وتُرسل عبر بوابة المريض. أي نتيجة تحتاج مناقشة، يتصل بك الطبيب في نفس يوم وصولها.'],
            ['can-i-cancel-or-reschedule', 'Can I cancel or reschedule?', 'هل يمكنني الإلغاء أو تغيير الموعد؟',
                'Yes, up to twelve hours before the appointment at no charge. Later than that, or a missed appointment, is charged at half the consultation fee.',
                'نعم، حتى اثنتي عشرة ساعة قبل الموعد بدون رسوم. بعد ذلك، أو التخلّف عن الموعد، يُحسب بنصف قيمة الاستشارة.'],
            ['do-you-see-children', 'Do you see children?', 'هل تستقبلون الأطفال؟',
                'Yes, from birth onwards in the paediatric clinic. Children under sixteen must be accompanied by a parent or legal guardian.',
                'نعم، من الولادة وما بعدها في عيادة الأطفال. يجب أن يكون الأطفال تحت السادسة عشرة بصحبة أحد الوالدين أو الوصي.'],
            ['is-parking-available', 'Is parking available?', 'هل يتوفر موقف سيارات؟',
                'There are fourteen patient spaces behind the building, free for the duration of your appointment. Two are reserved for accessible parking.',
                'يوجد أربعة عشر موقفاً للمرضى خلف المبنى، مجاناً لمدة موعدك. اثنان منها مخصصان لذوي الاحتياجات الخاصة.'],
        ];

        foreach ($rows as [$slug, $qEn, $qAr, $aEn, $aAr]) {
            $this->upsertEntry('faqs', $slug, [
                'question' => ['en' => $qEn, 'ar' => $qAr],
                'answer' => ['en' => $aEn, 'ar' => $aAr],
            ]);
        }
    }

    // ─────────────────────────────────────────────────────────────────────
    // Write helpers
    // ─────────────────────────────────────────────────────────────────────

    /**
     * Create an entry with its values, or return the existing one untouched.
     *
     * @param  array<string, array<string, string>>  $values  field => [lang => value]
     */
    private function upsertEntry(
        string $typeSlug,
        string $slug,
        array $values,
        ?Carbon $publishedAt = null
    ): int {

        $typeId = $this->types[$typeSlug];

        // (project_id, slug) is unique, so this is the natural key.
        $existing = DB::table('data_entries')
            ->where('project_id', $this->projectId)
            ->where('slug', $slug)
            ->value('id');

        if ($existing !== null) {
            return (int) $existing;
        }

        $publishedAt ??= Carbon::now()->subDays(random_int(1, 90));

        $entryId = (int) DB::table('data_entries')->insertGetId([
            'slug' => $slug,
            'data_type_id' => $typeId,
            'project_id' => $this->projectId,
            'status' => 'published',
            'published_at' => $publishedAt,
            'created_by' => $this->ownerId,
            'updated_by' => $this->ownerId,
            'created_at' => $publishedAt,
            'updated_at' => $publishedAt,
        ]);

        $rows = [];

        foreach ($values as $fieldName => $byLanguage) {

            $fieldId = $this->fields[$typeSlug][$fieldName] ?? null;

            if ($fieldId === null) {
                continue;
            }

            foreach ($byLanguage as $language => $value) {
                $rows[] = [
                    'data_entry_id' => $entryId,
                    'data_type_field_id' => $fieldId,
                    'language' => $language,
                    'value' => $value,
                    'created_at' => now(),
                    'updated_at' => now(),
                ];
            }
        }

        if ($rows !== []) {
            DB::table('data_entry_values')->insert($rows);
        }

        return $entryId;
    }

    private function report(): void
    {
        $counts = [];

        foreach ($this->types as $slug => $typeId) {
            $counts[] = [
                $slug,
                DB::table('data_entries')->where('data_type_id', $typeId)->count(),
            ];
        }

        $this->command?->info(
            "Clinic project [".self::PROJECT_SLUG."] #{$this->projectId} seeded."
        );
        $this->command?->table(['Data type', 'Entries'], $counts);
    }
}
