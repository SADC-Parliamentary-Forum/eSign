-- SADC-eSign Database Initialization Script
-- This runs when PostgreSQL container is first created

-- Enable UUID extension
CREATE EXTENSION IF NOT EXISTS "uuid-ossp";

-- Enable pgcrypto for encryption functions
CREATE EXTENSION IF NOT EXISTS "pgcrypto";

-- Grant privileges (if needed for multi-database setup)
GRANT ALL PRIVILEGES ON DATABASE sadc_esign TO sadc_esign;
