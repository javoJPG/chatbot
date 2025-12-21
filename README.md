# Bot Híbrido de WhatsApp

Bot inteligente para WhatsApp que combina respuestas automáticas con inteligencia artificial para ventas de cuentas de streaming.

## 🚀 Configuración

### 1. Variables de Entorno

Copia el archivo de ejemplo y configura tus credenciales:

```bash
cp .env.example .env
```

Edita el archivo `.env` con tus credenciales:

```env
# GreenAPI
GREEN_ID=tu_id_instance
GREEN_TOKEN=tu_token
GREEN_API_URL=https://7105.api.greenapi.com

# OpenAI
OPENAI_API_KEY=tu_openai_api_key
OPENAI_MODEL=gpt-4o-mini
VISION_MODEL=gpt-4o-mini

# Configuración adicional
MAX_DAYS_SIN_PAYMENT=1
```

### 2. Obtener Credenciales

#### GreenAPI
1. Regístrate en [GreenAPI](https://green-api.com/)
2. Crea una instancia
3. Copia el `idInstance` y `apiTokenInstance`

#### OpenAI
1. Regístrate en [OpenAI](https://platform.openai.com/)
2. Ve a [API Keys](https://platform.openai.com/api-keys)
3. Crea una nueva API key

## 🐳 Docker

### Construir la imagen:
```bash
docker build -t chatbot .
```

### Ejecutar con variables de entorno:
```bash
docker run -d \
  -p 80:80 \
  -e GREEN_ID=tu_id \
  -e GREEN_TOKEN=tu_token \
  -e GREEN_API_URL=https://7105.api.greenapi.com \
  -e OPENAI_API_KEY=tu_key \
  chatbot
```

### O usar un archivo .env:
```bash
docker run -d -p 80:80 --env-file .env chatbot
```

## 📝 Notas

- El archivo `.env` está en `.gitignore` por seguridad
- No subas tus credenciales al repositorio
- Usa `.env.example` como plantilla para otros desarrolladores

