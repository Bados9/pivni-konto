FROM node:20-alpine

WORKDIR /app

# Node image already has user 'node' with uid/gid 1000
RUN chown -R node:node /app

USER node

EXPOSE 5173

CMD ["npm", "run", "dev"]
