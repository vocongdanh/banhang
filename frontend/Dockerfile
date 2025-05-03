# Use a specific Node.js version
FROM node:18-alpine

WORKDIR /app

# Install dependencies including Next.js globally
COPY package*.json ./
RUN npm install
RUN npm install -g next

# Copy project files
COPY . .

# Expose port 3000
EXPOSE 3000

# Start the application - will be overridden by docker-compose command
CMD ["npm", "start"] 