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

$_['entry_info']          = 'Інформація';
$_['entry_char_quantity'] = 'Кількість символів доступних для перекладу';
$_['entry_analization']     = 'Аналізувати не переведені рядки';
$_['entity_begin_analization']     = 'Аналізувати';
$_['entry_analization_mode']     = 'Режим аналізу';
$_['entry_stop_modal_process']          = 'Зупинити';
$_['entry_stop_modal_process_confirmation']          = 'Ви дійсно хочете зупинити операцію?';
$_['entry_close']     = 'Закрити';
$_['entry_try_translate_text']     = 'Форма перевірки перекладу';
$_['entry_translate_expert_version']     = 'Версія модуля';
$_['entry_php_version']     = 'Версія PHP';
$_['entry_opencart_version']= 'Версія Opencart';
$_['entry_debug_status']     = 'Статус режима відлагодження';
$_['entry_table']     = 'Таблиця';
$_['entry_table_deep']     = 'Глибокий аналіз';
$_['entry_table_deep_description']     = 'Повний аналіз таблиці (Вимкніть якщо аналіз відбувається дуже довго)';

// видалити переклад
$_['entry_remove_translation_records_from_db'] = 'Видалити значення перекладу з бази даних';
$_['entity_begin_removing_data_from_db'] = 'Видалити переклад';
$_['entry_remove_translation_records_from_db_confirmation'] = 'УВАГА! Ми настійно рекомендуємо зробити резервну копію бази даних. Ви впевнені, що бажаєте видалити значення перекладу мови {langCode} з бази даних?';
$_['entry_removing_data_from_db_process_is_started'] = 'Розпочато видалення даних із бази даних.';

// кількість символів
$_['entry_server_show_translation_char_count_globally'] = 'Показати глобальну кількість символів перекладу в заголовку';
$_['entry_server_reset_translation_char_count_on_start_month'] = 'Скинути кількість символів для перекладів на початку кожного місяця';
$_['entry_server_translation_char_count'] = 'Кількість символів перекладу';

// виключені маршрути
$_['entry_excluded_routes'] = 'Не завантажувати модуль на цих сторінках';
$_['help_excluded_routes'] = 'Вкажіть один маршрут сторінки адмінки в кожному рядку. Додайте <code>*</code> у кінці рядка, щоб вибрати всі маршрути, які починаються з цього тексту.<br>Приклади:<br><code>catalog/product*</code> — список товарів і всі сторінки товару<br><code>extension/module/my_module</code> — сторінка модуля в OpenCart 2.3–3.0<br><code>module/my_module</code> — сторінка модуля в OpenCart 1.5–2.2<br><code>extension/my_extension/module/my_module</code> — сторінка модуля в OpenCart 4<br>Можна також вставити повну URL-адресу сторінки адмінки. На сторінці налаштувань цього модуля модуль завантажується завжди.';

// скинути кеш
$_['entry_reset_translation_cache'] = 'Скинути кеш перекладу';



// Tabs
$_['tab_general']        = 'Налаштування';
$_['tab_translate_site'] = 'Контент сайту';
$_['tab_localization']   = 'Файли магазину';
$_['tab_admin_localization']   = 'Файли адмінки';
$_['tab_debug']          = 'Відлагодження';
$_['tab_about']          = 'Про модуль';

// Settings, Debug and About tabs (card titles)
$_['te_settings_card_connection'] = 'Підключення';
$_['te_settings_card_usage'] = 'Використання символів';
$_['te_settings_card_cron'] = 'Переклад за розкладом (cron)';
$_['te_settings_card_routes'] = 'Виключені маршрути';
$_['te_settings_card_language_codes'] = 'Коди мов';
$_['te_settings_card_try'] = 'Пробний переклад';
$_['te_debug_card_versions'] = 'Версії';
$_['te_debug_card_cache'] = 'Кеш';
$_['te_cache_reset_done'] = 'Кеш перекладів скинуто.';
$_['te_cache_reset_confirm'] = 'Ви впевнені, що хочете скинути кеш перекладів? Модуль видалить збережені дані перекладів.';
$_['te_about_card'] = 'Інформація про модуль';

// Value
$_['value_analization_mode_only_empty'] = 'Шукати тільки порожні значення';
$_['value_analization_mode_same_value'] = 'Шукати однакові значення';
$_['value_analization_mode_both'] = 'Шукати порожні і однакові значення';


// js constants

// Error
$_['error_permission'] = 'У вас немає прав для управління цим модулем!';
$_['error_bad_filter'] = 'Значення фільтрів недопустимі. Перезавантажте сторінку та виберіть фільтри знову.';
$_['error_internal'] = 'Внутрішня помилка.';
$_['error_wrong_method'] = 'Невірний метод запиту.';
$_['error_language_to_required'] = 'Виберіть мову, з якої потрібно видалити переклади.';
$_['te_site_error_bad_detail_request'] = 'Таблицю, колонку або мову вказано неправильно.';
$_['error_width']      = 'Необхідно вказати ширину!';
$_['error_height']     = 'Необхідно вказати висоту!';
$_['error_unlicensed'] = 'Ви використовуєте неліцензійну версію модуля!<br>Рекомендуємо купити модуль на сайті <a href="https://github.com/vhoshko/opencart-translate-expert" target="_blank">https://github.com/vhoshko/opencart-translate-expert</a> або звернутись в підтримку модуля через <a href="https://github.com/vhoshko/opencart-translate-expert/issues" target="_blank">GitHub Issues</a>';
$_['error_debug_log_warning']	   = 'Попередження: Ваш файл журналу налагодження %s - %s!';

$_['error_lang_from_folder_is_not_exists']	   = 'Папка файлів локалізації для "Мова з якої перекладати" не знайдена: ';
$_['error_lang_from_and_lang_to_are_equal']	   = 'Виберіть мову на яку перекладати';
$_['error_localization_save_target_invalid']	   = 'Неправильна цільова тека або шлях до файлу. Файл не збережено.';
$_['error_localization_save_failed']	   = 'Файл не збережено. Перевірте, що веб-сервер може записувати в цю теку:';
$_['error_localization_save_empty']	   = 'Файл порожній. Нічого зберігати.';




$_['entry_table_product_filter'] = 'Фільтр продуктів';
$_['entry_table_product_status_filter'] = 'Фільтр статусу продукту, тільки активні продукти';
$_['entry_table_product_quantity_filter'] = 'Фільтр кількості товару, тільки продукти з кількістю > 0)';
$_['entry_table_product_category_id_filter'] = 'Фільтр по категорії';
$_['entry_table_product_stock_status_filter'] = 'Фільтр по стану товару на складі';
$_['entry_table_product_manufacturer_filter'] = 'Фільтр по виробнику товару';

// Front-end strings of the new admin design (document.te_i18n on the module page)
$_['te_alert_details'] = 'Докладніше';
$_['te_alert_close'] = 'Закрити';
$_['te_alert_not_json'] = 'Сервер не надіслав відповідь у форматі JSON.';
$_['te_summary_month'] = 'У цьому місяці: {count} / {limit}';
$_['te_summary_cost'] = 'Вартість понад безкоштовний ліміт: {cost}';
$_['te_remove_completed'] = 'Видалення даних з бази даних завершено: {language}.';
$_['te_remove_start'] = 'Видалення даних з бази даних розпочато.';
$_['te_remove_table'] = 'Таблиця {table}: очищено значень: {rows}.';
$_['te_remove_column'] = '- {column}: очищено значень: {rows}.';
$_['te_remove_finish'] = 'Видалення даних з бази даних завершено.';
$_['te_time_in_sec'] = 'Час: {sec} сек.';
$_['te_summary_status'] = 'Статус';
$_['te_summary_enabled'] = 'Увімкнено';
$_['te_summary_disabled'] = 'Вимкнено';
$_['te_summary_api_key'] = 'API-ключ';
$_['te_summary_key_set'] = 'Вказано';
$_['te_summary_key_not_set'] = 'Не вказано';

// Front-end strings for all admin pages (document.te_i18n_global, written by the admin header)
$_['te_global_char_count'] = 'Перекладено символів';
$_['te_global_reset'] = 'Скинути';
$_['te_global_reset_confirm'] = 'Ви впевнені, що хочете скинути кількість символів перекладу?';
$_['te_global_translate'] = 'Перекласти';
$_['te_global_error_internal'] = 'Внутрішня помилка, подробиці в журналі консолі!';

// Website content tab (document.te_i18n, te_site_* )
$_['te_site_lang_from'] = 'Перекладати з';
$_['te_site_lang_to'] = 'на';
$_['te_site_lang_swap'] = 'Поміняти мови місцями';
$_['te_site_card_what'] = 'Що перекладати';
$_['te_site_more_filters'] = 'Додаткові фільтри';
$_['te_site_stop'] = 'Зупинити';
$_['te_site_log'] = 'Журнал';
$_['te_site_analyzing'] = 'Виконується аналіз';
$_['te_site_translating'] = 'Переклад: {table}';
$_['te_site_col_table'] = 'Таблиця / колонка';
$_['te_site_col_chars'] = 'Символи';
$_['te_site_col_chars_lang'] = 'Символи ({code})';
$_['te_site_col_actions'] = 'Дії';
$_['te_site_total'] = 'Разом';
$_['te_site_translate'] = 'Перекласти';
$_['te_site_translate_all'] = 'Перекласти все';
$_['te_site_details'] = 'Докладно';
$_['te_site_not_supported'] = 'не підтримується';
$_['te_site_done'] = 'готово';
$_['te_site_toggle'] = 'Показати або сховати колонки таблиці {table}';
$_['te_site_cnt_current'] = 'Зараз: {current}';
$_['te_site_cnt_table'] = 'Таблиця {n} з {total}';
$_['te_site_cnt_request'] = 'Запит {request}';
$_['te_site_cnt_chars'] = 'Знайдено символів: {chars}';
$_['te_site_cnt_time'] = 'Час: {sec} с';
$_['te_site_cnt_translated'] = 'Перекладено символів: {chars}';
$_['te_site_translate_confirm'] = 'Ви дійсно хочете перекласти {table} ({lang})?';
$_['te_site_translate_all_confirm'] = 'Ви дійсно хочете перекласти всі таблиці зі списку ({lang})?';
$_['te_site_log_table_done'] = 'Готово: {table}, перекладено символів: {chars}';
$_['te_site_detail_loading'] = 'Підготовка детальної інформації ...';
$_['te_site_detail_source'] = 'Оригінал ({code})';
$_['te_site_detail_target'] = 'Переклад ({code})';
$_['te_site_empty'] = 'порожньо';
$_['te_site_identical'] = 'збігається';
$_['te_site_detail_none'] = 'Немає значень.';
$_['te_site_detail_more'] = 'Показано лише перші 100 значень.';
$_['te_site_state_done'] = 'Завершено';
$_['te_site_state_stopped'] = 'Зупинено';
$_['te_site_state_error'] = 'Помилка';
$_['te_loc_card_files'] = 'Файли локалізації';
$_['te_loc_analyze'] = 'Аналізувати файли';
$_['te_loc_translate_all'] = 'Перекласти все';
$_['te_loc_analyzing'] = 'Триває аналіз файлів';
$_['te_loc_translating'] = 'Переклад: {path}';
$_['te_loc_cnt_files'] = 'Файли {n} з {total}';
$_['te_loc_totals_files'] = 'Файлів: {count}';
$_['te_loc_totals_to_translate'] = 'Файлів для перекладу: {count}';
$_['te_loc_totals_values'] = 'Значень для перекладу: {count}';
$_['te_loc_totals_chars'] = 'Символів для перекладу: {count}';
$_['te_loc_col_file'] = 'Файл';
$_['te_loc_col_keys'] = 'Ключів для перекладу';
$_['te_loc_col_chars'] = 'Символів для перекладу';
$_['te_loc_col_state'] = 'Стан';
$_['te_loc_state_missing'] = 'немає файла';
$_['te_loc_state_partial'] = 'частково';
$_['te_loc_state_saved'] = 'збережено';
$_['te_loc_toggle'] = 'Показати або сховати ключі файла {path}';
$_['te_loc_root'] = 'Розширення: {name}';
$_['te_loc_expression'] = 'PHP-код, копіюється без змін';
$_['te_loc_source_file'] = 'Вихідний файл';
$_['te_loc_translate_file'] = 'Перекласти файл';
$_['te_loc_translate_save_file'] = 'Перекласти і зберегти файл';
$_['te_loc_save_file'] = 'Зберегти файл';
$_['te_loc_saved'] = 'Збережено: {path}';
$_['te_loc_nothing_to_save'] = 'Нічого зберігати: {path}';
$_['te_loc_no_files'] = 'Усі файли перекладено.';
$_['te_loc_translate_all_confirm'] = 'Ви впевнені, що хочете перекласти та зберегти всі файли зі списку ({lang})?';

// language code map
$_['entry_language_code_map'] = 'Карта кодів мов';
$_['help_language_code_map'] = 'Зіставляє код мови магазину з кодом, який використовує Google Translate. Приклад: ua в uk. Модуль замінює код перед викликом Google. Код без пари залишається як є.';
$_['error_language_code_map'] = 'Карта кодів мов некоректна. Модуль не зберіг налаштування.';
$_['te_lcm_add'] = 'Додати пару';
$_['te_lcm_reset'] = 'Повернути типові значення';
$_['te_lcm_col_key'] = 'Код магазину';
$_['te_lcm_col_value'] = 'Код Google';
$_['te_lcm_empty'] = 'Карта порожня. Модуль надсилає всі коди в Google без змін.';
$_['te_lcm_delete'] = 'Видалити пару';
$_['te_lcm_reset_confirm'] = 'Ви впевнені, що хочете скинути карту до типових пар? Щоб зберегти зміну, збережіть налаштування.';
$_['te_lcm_err_empty_key'] = 'Код магазину порожній.';
$_['te_lcm_err_empty_value'] = 'Код Google порожній.';
$_['te_lcm_err_duplicate'] = 'Код магазину повторюється в списку.';
$_['te_lcm_err_chars'] = 'Код може містити лише символи A-Z, a-z, 0-9, - і _.';
$_['te_lcm_err_length'] = 'Код може містити не більше 20 символів.';
$_['te_lcm_err_count'] = 'Карта може містити не більше 100 пар.';
$_['te_lcm_row_label'] = 'Пара';
