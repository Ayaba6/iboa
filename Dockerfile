# --- Étape 1 : Build des assets ---
FROM node:20-alpine AS build-stage
WORKDIR /app
COPY package*.json ./
RUN npm install --legacy-peer-deps
COPY . .
RUN npm run build

# --- Étape 2 : Serveur de production Nginx ---
FROM nginx:alpine
# Copier les fichiers buildés par Vite vers le dossier public de Nginx
COPY --from=build-stage /app/dist /usr/share/nginx/html

# (Optionnel) Copier une config Nginx personnalisée pour le routage SPA si nécessaire
# COPY nginx.conf /etc/nginx/conf.d/default.conf

EXPOSE 80
CMD ["nginx", "-g", "daemon off;"]