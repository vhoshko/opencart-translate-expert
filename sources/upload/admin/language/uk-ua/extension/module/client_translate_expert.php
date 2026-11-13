<?php
// Heading
$_['heading_title']    = '<img src="view/image/translate_expert_client/translate-icon-32.png" alt="" style="height:1em;width:auto;vertical-align:-0.125em;margin-right:6px;" />Translate <span style="color:#f56b00;">Expert</span> (Full)';

// Text
$_['text_extension']   = 'Модулі';
$_['text_success']     = 'Налаштування модуля оновлені!';
$_['text_edit']        = 'Редагування модуля';

$_['text_login_is_correct']        = '<div class="translate_expert_correct">Перевірка авторизації пройшла успішно.</div>';
$_['text_need_to_login']        = '<div class="translate_expert_incorrect">Перевірка автентичності НЕ успішна.</div><br>Для отримання ключа зареєструйтесь або авторизуйтесь на сайті <a href="https://github.com/vhoshko/opencart-translate-expert" target="_blank">https://github.com/vhoshko/opencart-translate-expert</a>';
$_['text_need_to_pay']        = '<div class="translate_expert_incorrect">У Вас закінчилися або залишилося дуже мало символів для перекладу.</div><br>Перейдіть на сайт щоб купити додаткові символи <a href="https://github.com/vhoshko/opencart-translate-expert" target="_blank">https://github.com/vhoshko/opencart-translate-expert</a>';
$_['text_no_need_to_pay']        = '<div class="translate_expert_correct">У вас ще багато символів для перекладу.</div>';
$_['text_about_page']        = '<div class="panel-body"><h2>Дякуємо що користуєтесь нашим модулем!</h2><h4>Технічна підтримка <a href="https://github.com/vhoshko/opencart-translate-expert/issues" target="_blank"><b>GitHub Issues</b></a></h4><div class="alert bg-success"><h4>Модуль тестувався на стандартних сторінках адмін частини, створіть issue на GitHub якщо Вам потрібна адаптація під будь які інші сторінки.<p></p><p>Всім користувачам надається безкоштовна консультація та підтримка по роботі модулю через GitHub Issues.</p></h4></div></div>';
$_['text_current_module_version']        = 'Поточна версія модуля <b>{current_module_version}</b>';
$_['text_newest_module_version']        = 'Найновіша доступна версія модуля <b>{newest_module_version}</b>';
$_['text_you_need_to_update_module']        = '<div class="translate_expert_incorrect">Для коректної роботи модуля рекомендуємо Вам оновити модуль до новішої версії. <br>Ви завжди можете знайти найновішу версію на <a href="https://github.com/vhoshko/opencart-translate-expert/releases" target="_blank">https://github.com/vhoshko/opencart-translate-expert/releases</a></div>';
$_['text_you_have_the_newest_version_of_module']        = '<div class="translate_expert_correct">Вітаємо у Вас найновіша версія модуля.</div>';
$_['text_debug_log']        = 'Журнал відлагодження';
$_['text_clear_debug_log_success']	   = 'Успіх: Ви успішно очистили журнал відлагодження!';
$_['text_clear_confirm']	   = 'Чи справді Ви бажаєте видалити файл журналу відлагодження?';

// Entry
$_['entry_server_key'] = 'Google Translate Api Key (Ключ)';
$_['button_show_key'] = 'Показати ключ';
$_['button_hide_key'] = 'Сховати ключ';
$_['button_cron_secret_generate'] = 'Згенерувати';
$_['entry_cron_bulk_amount'] = 'Рядків за один запуск cron';
$_['help_cron_bulk_amount'] = 'Посилання для cron перекладає до цієї кількості рядків бази даних за один запуск (від 1 до 100000). Це число визначає розмір запуску. Запуск зупиняється не пізніше ніж через 15 хвилин. Обмеження PHP max_execution_time на хостингу і тайм-аут планувальника все одно діють, тому виберіть число, яке встигне виконатися. Посилання для cron нижче містить це число в параметрі bulk_amount. Якщо ви змінюєте число, скопіюйте нове посилання в планувальник. Посилання без bulk_amount використовує збережене значення.';
$_['entry_cron_secret'] = 'Секрет для cron';
$_['help_cron_secret'] = 'Секрет для посилання cron: від 16 до 128 символів, лише літери, цифри, <code>-</code> і <code>_</code>. Натисніть кнопку, щоб створити випадковий секрет. Якщо секрет порожній, посилання для cron вимкнене. Збережіть налаштування, щоб застосувати зміну.';
$_['entry_cron_link'] = 'Посилання для cron';
$_['help_cron_link'] = 'Викликайте це посилання з cron, щоб перекладати дані магазину без сторінки адмінки. Приклад для crontab:<br><code>*/10 * * * * wget -q -O - "&lt;посилання&gt;"</code><br>Посилання використовує фільтри, вибрані зараз у налаштуваннях аналізу нижче, і секрет із поля "Секрет для cron". Воно перекладає всі текстові колонки вибраних таблиць. Якщо таблицю не вибрано, обробляються всі таблиці по порядку. Кожен виклик перекладає одну порцію і повертає JSON: <code>{"done":false,...}</code>. Викликайте посилання знову, поки <code>done</code> не стане <code>true</code>. Кожен запуск зупиняється не пізніше ніж через 15 хвилин. Обмеження PHP max_execution_time на хостингу і тайм-аут планувальника (наприклад, тайм-аут читання wget за замовчуванням) все одно діють, тому виберіть кількість рядків, яка встигне виконатися. Одночасно працює лише один запуск. Режим обслуговування блокує посилання. Не показуйте посилання стороннім: секрет — частина посилання.';
$_['help_multi_select'] = 'Утримуйте Ctrl (Cmd на Mac) і клацніть, щоб вибрати кілька значень. Утримуйте Shift і клацніть, щоб вибрати діапазон. Клацніть у списку та натисніть Ctrl+A (Cmd+A на Mac), щоб вибрати всі значення. Якщо нічого не вибрано, використовуються всі значення.';
$_['button_select_all'] = 'Вибрати всі';
$_['help_multi_select_limit'] = 'Можна вибрати не більше %s елементів.';
$_['button_select_first'] = 'Вибрати перші %s';
$_['warning_cron_link_long'] = 'Посилання довше за %s символів. Деякі сервери та панелі cron не приймають таке довге посилання. Виберіть менше елементів у фільтрах.';
$_['button_clear_selection'] = 'Очистити вибір';
$_['error_cron_secret'] = 'Посилання для cron вимкнене. Секрет для cron порожній або неправильний (від 16 до 128 символів). Введіть секрет або натисніть кнопку "Згенерувати", потім збережіть налаштування.';
$_['text_cron_secret_unsaved'] = 'Секрет не збережено. Посилання для cron запрацює лише після збереження налаштувань.';
$_['entry_limit']      = 'Ліміт';
$_['entry_width']      = 'Ширина';
$_['entry_height']     = 'Висота';
$_['entry_status']     = 'Статус';
$_['entry_login']     = 'Ваш логін';
$_['entry_name']     = 'Назва';
$_['entity_table']    = 'Таблиця';
$_['entity_column']   = 'Колонка';

$_['entry_info']          = 'Інформація';
$_['entry_char_quantity'] = 'Кількість символів доступних для перекладу';
$_['entry_analization']     = 'Аналізувати не переведені рядки';
$_['entity_begin_analization']     = 'Аналізувати';
$_['entry_analization_result']     = 'Результат аналізу';
$_['entry_analization_mode']     = 'Режим аналізу';
$_['entry_stop_modal_process']          = 'Зупинити';
$_['entry_stop_modal_process_confirmation']          = 'Ви дійсно хочете зупинити операцію?';
$_['entry_time_in_sec']          = 'Час: <strong id="timeInSec">{timeInSec}</strong> cекунд';
$_['entry_summ']          = 'Все языки';
$_['entry_close']     = 'Закрити';
$_['entry_try_translate']     = 'Форма перевірки перекладу<br>Тест API ключа';
$_['entry_try_translate_text']     = 'Форма перевірки перекладу';
$_['entry_translate_expert_version']     = 'Версія модуля';
$_['entry_php_version']     = 'Версія PHP';
$_['entry_opencart_version']= 'Версія Opencart';
$_['entry_debug_status']     = 'Статус режима відлагодження';
$_['entry_table']     = 'Таблиця';
$_['entry_analization_language_from'] = 'Мова з якої перекладати';
$_['entry_analization_language_to'] = 'Мова на яку перекладати';
$_['entry_table_deep']     = 'Глибокий аналіз';
$_['entry_table_deep_description']     = 'Повний аналіз таблиці (Вимкніть якщо аналіз відбувається дуже довго)';

// видалити переклад
$_['entry_remove_translation_records_from_db'] = 'Видалити значення перекладу з бази даних';
$_['entity_begin_removing_data_from_db'] = 'Видалити переклад';
$_['entry_remove_translation_records_from_db_confirmation'] = 'УВАГА! Ми настійно рекомендуємо зробити резервну копію бази даних. Ви впевнені, що бажаєте видалити значення перекладу мови {langCode} з бази даних?';
$_['entry_removing_data_from_db_process_is_started'] = 'Розпочато видалення даних із бази даних.';
$_['message_remove_data_from_db_start'] = 'Розпочато видалення даних із бази даних.';
$_['message_remove_data_from_table'] = 'Переклад таблиці {table} розпочато.';
$_['message_remove_data_from_table_column'] = '- {textColumnName}.';
$_['message_remove_data_from_db_finish'] = 'Видалення даних із бази даних завершено.';
$_['message_remove_data_from_db_completed'] = 'Видалення даних із бази даних завершено.';

// кількість символів
$_['entry_server_show_translation_char_count_globally'] = 'Показати глобальну кількість символів перекладу в заголовку';
$_['entry_server_reset_translation_char_count_on_start_month'] = 'Скинути кількість символів для перекладів на початку кожного місяця';
$_['entry_server_translation_char_count'] = 'Кількість символів перекладу';
$_['entry_server_translation_char_count_info'] = "
<div id='translation_char_count_info_container' title='Кількість перекладених символів: {charCount}. Безкоштовний місячний ліміт: {monthFreeLimit}. Приблизна вартість: {costOverFreeLimit}.'>
<div id='translation_char_count_info' class='{color}'>Кількість перекладених символів:<br>{charCount} / {monthFreeLimit} ({costOverFreeLimit})</div>
<div id='reset_translation_char_count' class='{color}' title='Скинути кількість перекладених символів' onclick='if (confirm(\"Ви впевнені, що хочете скинути кількість символів перекладу?\")) resetTranslationCharCount()'>Скинути</div>
</div>
";

// виключені маршрути
$_['entry_excluded_routes'] = 'Не завантажувати модуль на цих сторінках';
$_['help_excluded_routes'] = 'Вкажіть один маршрут сторінки адмінки в кожному рядку. Додайте <code>*</code> у кінці рядка, щоб вибрати всі маршрути, які починаються з цього тексту.<br>Приклади:<br><code>catalog/product*</code> — список товарів і всі сторінки товару<br><code>extension/module/my_module</code> — сторінка модуля в OpenCart 2.3–3.0<br><code>module/my_module</code> — сторінка модуля в OpenCart 1.5–2.2<br><code>extension/my_extension/module/my_module</code> — сторінка модуля в OpenCart 4<br>Можна також вставити повну URL-адресу сторінки адмінки. На сторінці налаштувань цього модуля модуль завантажується завжди.';

// скинути кеш
$_['entry_reset_translation_cache'] = 'Скинути кеш перекладу';

$_['entry_localization_language_from'] = 'Мова з якої перекладати';
$_['entry_localization_language_to'] = 'Мова на яку перекладати';
$_['entry_localization_analization'] = 'Аналізувати не переведені значення';
$_['entry_localization_analization_result'] = 'Результат аналізу';
$_['entry_localization_file_to_translate_info'] = '{path}: <b>Не перекладено {count} значень</b>';
$_['entry_localization_file_to_translate_info_full_translated'] = '{path}: Повністю перекладений';
$_['entry_localization_file_expression_info'] = '<b>({count} з PHP-кодом: копіюються без змін, відредагуйте їх вручну)</b>';
$_['entry_localization_file_target_missing'] = '<b>(цільовий файл не існує)</b>';

$_['entry_fileCount'] = 'Файлів всього';
$_['entry_needToTranslateFileCount'] = 'Файлів не перекладено';
$_['entry_needToTranslateValueCount'] = 'Значень не перекладено';
$_['entry_needToTranslateCharCount'] = 'Кількість символів не перекладено';
$_['entry_TranslateAll'] = 'Перекласти все';

// Tabs
$_['tab_general']        = 'Загальна';
$_['tab_translate_site'] = 'Переклад контенту сайту';
$_['tab_localization']   = 'Переклад файлів локалізації';
$_['tab_admin_localization']   = 'Переклад файлів локалізації адмінки';
$_['tab_debug']          = 'Відлагоджування';
$_['tab_about']          = 'Про модуль';

// Value
$_['value_analization_mode_only_empty'] = 'Шукати тільки порожні значення';
$_['value_analization_mode_same_value'] = 'Шукати однакові значення';
$_['value_analization_mode_both'] = 'Шукати порожні і однакові значення';

// Html Title attributes
$_['title_translate_all_tables'] = 'Перевести всі таблиці';
$_['title_translate_table'] = 'Перекласти таблицю {table}';
$_['title_translate_table_column'] = 'Перекласти колонку {textColumnName} таблиці {table}';
$_['title_translate_table_confirmation'] = 'Ви дійсно хочете {translateTableTitle}?';
$_['title_translate_to_lang_suffix'] = ' (мова {langCode})';
$_['title_not_translater_char_count']          = 'Кількість неперекладених символів';

// js constants
$_['js_const_done_status'] = 'Завершено!';
$_['js_const_in_progress_status'] = 'В процесі...';
$_['js_const_statistic_html'] = "<div id='statusLog'>{historyLog}</div><div id='statusCurrent'>Таблиця <strong>{table}</strong>, переведено: <strong class='statusTranslatedCharCount'>{translatedCharCount}</strong>, Статус: <strong class='translatedStatus'>{status}</strong></div>";
$_['js_const_analization_process_is_started'] = "Виконується процес аналізу...<br>Талиця: <strong id='analization_current_table'></strong><br>Номер запиту: <strong id='analization_request_number'>1</strong>";
$_['js_const_saved'] = 'Збережено!';
$_['js_const_localization_translate_progress'] = 'Переклад файлу локалізації {currIndex} з {count}: {path}.';
$_['js_const_localization_translate_done'] = 'Переклад файлу локалізації {currIndex} з {count}: Готово!';
$_['js_const_preparing_detail_info'] = 'Підготовка детальної інформації ...';
$_['button_localization_translate'] = 'Перекласти';
$_['button_localization_save'] = 'Зберегти';

// Error
$_['error_permission'] = 'У вас немає прав для управління цим модулем!';
$_['error_bad_filter'] = 'Значення фільтрів недопустимі. Перезавантажте сторінку та виберіть фільтри знову.';
$_['error_width']      = 'Необхідно вказати ширину!';
$_['error_height']     = 'Необхідно вказати висоту!';
$_['error_unlicensed'] = 'Ви використовуєте неліцензійну версію модуля!<br>Рекомендуємо купити модуль на сайті <a href="https://github.com/vhoshko/opencart-translate-expert" target="_blank">https://github.com/vhoshko/opencart-translate-expert</a> або звернутись в підтримку модуля через <a href="https://github.com/vhoshko/opencart-translate-expert/issues" target="_blank">GitHub Issues</a>';
$_['error_debug_log_warning']	   = 'Попередження: Ваш файл журналу налагодження %s - %s!';

$_['error_lang_from_folder_is_not_exists']	   = 'Папка файлів локалізації для "Мова з якої перекладати" не знайдена: ';
$_['error_lang_from_and_lang_to_are_equal']	   = 'Виберіть мову на яку перекладати';
$_['error_localization_save_target_invalid']	   = 'Неправильна цільова тека або шлях до файлу. Файл не збережено.';
$_['error_localization_save_failed']	   = 'Файл не збережено. Перевірте, що веб-сервер може записувати в цю теку:';
$_['error_localization_save_empty']	   = 'Файл порожній. Нічого зберігати.';

// Messages
$_['message_analize_global_info']     = '<b>Всі таблиці</b>';
$_['message_analize_table_head_info']     = '<strong class="et_table" id="et_{table}">{table}</strong>';
$_['message_analize_field_head_info']     = 'Текстове поле <strong class="et_field" id="et_{table}_{textColumnName}">{textColumnName}</strong>';



$_['entry_table_product_filter'] = 'Фільтр продуктів';
$_['entry_table_product_status_filter'] = 'Фільтр статусу продукту, тільки активні продукти';
$_['entry_table_product_quantity_filter'] = 'Фільтр кількості товару, тільки продукти з кількістю > 0)';
$_['entry_table_product_category_id_filter'] = 'Фільтр по категорії';
$_['entry_table_product_stock_status_filter'] = 'Фільтр по стану товару на складі';
$_['entry_table_product_manufacturer_filter'] = 'Фільтр по виробнику товару';
