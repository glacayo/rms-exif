# 🚀 RMS Image Optimizer — Local SEO & Google Business Profile

[![PHP Version](https://img.shields.io/badge/PHP-7.4+-777BB4?style=flat-square&logo=php&logoColor=white)](https://www.php.net/)
[![ExifTool](https://img.shields.io/badge/ExifTool-v12.0+-orange?style=flat-square)](https://exiftool.org/)
[![License](https://img.shields.io/badge/License-MIT-green?style=flat-square)](LICENSE)

**RMS Image Optimizer** es una herramienta profesional diseñada para especialistas en SEO y agencias de marketing digital. Permite optimizar imágenes específicamente para **Local SEO** y **Google Business Profile (GBP)**, inyectando metadatos críticos que ayudan a mejorar el posicionamiento geográfico.

---

## ✨ Características Principales

- **🛡️ Inyección de Metadatos EXIF**: Agregá descripción de imagen, etiquetas (keywords), autor y copyright directamente en el archivo.
- **📍 Geolocalización GPS**: Incrustá coordenadas exactas (Latitud/Longitud) para que Google asocie tus fotos con una ubicación física real.
- **📂 SEO Filename Builder**: Generador automático de nombres de archivo optimizados (minúsculas, sin acentos, separados por guiones).
- **⚡ Optimización y Compresión**: Reducción de peso sin pérdida de calidad perceptible, optimizado para una carga rápida.
- **📏 Cambio de Tamaño (Resizing)**: Ajustá las dimensiones a los estándares recomendados (1280x720, 1200x900, etc.).
- **🔄 Conversión de Formatos**: Transformá archivos PNG o WebP a JPEG optimizado automáticamente.
- **📦 Procesamiento por Lotes**: Subí varias imágenes a la vez y descargá el resultado individualmente o en un archivo ZIP.

---

## 🛠️ Requisitos del Sistema

Para que la aplicación funcione correctamente, tu servidor debe cumplir con:

- **PHP 7.4 o superior** (Compatible con PHP 8.1/8.2).
- **Extensión GD de PHP** activa (para el procesamiento de imágenes).
- **Permisos de escritura** en las carpetas `/tmp` y `/output`.
- **ExifTool** instalado o disponible en la raíz del proyecto.

---

## 📥 Descargas de Terceros Obligatorias

Esta aplicación utiliza **ExifTool**, una biblioteca externa ultrapotente para la manipulación de metadatos. Sin esto, la aplicación no podrá escribir el GPS ni los Tags en las fotos.

| Componente | Descripción | Vínculo de Descarga |
| :--- | :--- | :--- |
| **ExifTool (Phil Harvey)** | Motor principal para edición de metadatos (EXIF/IPTC/XMP). | [Descargar ExifTool](https://exiftool.org/) |
| **PHP (Windows/Laragon)** | Entorno de servidor recomendado para correrlo localmente. | [Descargar Laragon](https://laragon.org/download/) |

> [!IMPORTANT]
> Si estás en **Windows**, descargá el `exiftool(-k).exe`, renombralo a `exiftool.exe` y colocalo en la carpeta raíz del proyecto.

---

## 🚀 Instalación y Uso

1. **Cloná el repositorio** o descargá los archivos en tu carpeta de servidor (ej. `www/rms-exif`).
2. **Descargá ExifTool** desde el link oficial arriba y ponelo en la raíz si estás en Windows.
3. **Verificá los permisos**:
   Asegurate de que las carpetas `tmp/` y `output/` tengan permisos de lectura y escritura.
4. **Abrí la aplicación**:
   Navegá a `http://localhost/rms-exif/` (o la URL que corresponda).
5. **Diagnóstico**:
   Podes ejecutar `check_server.php` en tu navegador para verificar si todo está configurado correctamente.

---

## 💡 ¿Cómo usarlo?

1. **Paso 1: Upload**: Arrastrá tus fotos. Elegí la calidad y el tamaño deseado.
2. **Paso 2: SEO & Metadata**: 
   - Armá el nombre del archivo usando el "Filename Builder".
   - Escribí una descripción rica en palabras clave.
   - ¡No olvides las coordenadas GPS! Buscalas en Google Maps y pegalas.
3. **Paso 3: Preview & Export**: 
   - Compará el "Antes y Después" (verás cuánto peso ahorraste).
   - Descargá tu imagen lista para subir a Google.

---

## 📄 Licencia

Este proyecto es de uso libre bajo la licencia MIT.

---
*Desarrollado con ❤️ para la comunidad de SEO Real.*
