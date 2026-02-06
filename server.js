// server.js - Updated with Telegram integration
const express = require('express');
const mysql = require('mysql2/promise');
const bcrypt = require('bcryptjs');
const jwt = require('jsonwebtoken');
const cors = require('cors');
const https = require('https');
require('dotenv').config();

const app = express();

// Middleware
app.use(cors({
    origin: ['http://localhost:3000', 'http://127.0.0.1:5500', 'http://localhost:5500'],
    credentials: true
}));
app.use(express.json());
app.use(express.urlencoded({ extended: true }));

// Simple MySQL connection without pool for testing
let connection;

async function connectDB() {
    try {
        connection = await mysql.createConnection({
            host: 'localhost',
            user: 'root',
            password: '', // Your MySQL password here
            database: 'kroma_tech'
        });
        console.log('✅ Connected to MySQL database');
    } catch (error) {
        console.error('❌ Database connection error:', error.message);
        console.log('⚠️  Running in demo mode without database');
    }
}

// Telegram function
async function sendToTelegram(orderData, userInfo = null) {
    const TELEGRAM_BOT_TOKEN = '8504509149:AAGLc8ZLaV9ZI1CWGx1V-PRQjMNY88ubm2g';
    const YOUR_CHAT_ID = '8061490786';
    
    try {
        const telegramUsername = userInfo?.telegram || userInfo?.username || 'មិនទាន់បំពេញ';
        const userName = userInfo?.name || 'អតិថិជន';
        const userPhone = userInfo?.phone || 'មិនទាន់បំពេញ';
        
        let message = `🛒 **ការបញ្ជាទិញថ្មី - CodeGear**\n\n`;
        message += `👤 **អ្នកបញ្ជាទិញ:** ${userName}\n`;
        message += `📱 **ទូរស័ព្ទ:** ${userPhone}\n`;
        message += `✈️ **Telegram:** @${telegramUsername}\n\n`;
        
        if (orderData.shippingAddress) {
            message += `📍 **អាស័យដ្ឋាន:** ${orderData.shippingAddress}\n\n`;
        }
        
        message += `📦 **ផលិតផល:**\n`;
        
        if (orderData.items && Array.isArray(orderData.items)) {
            orderData.items.forEach((item, index) => {
                const itemName = item.name || 'ផលិតផល';
                const quantity = item.quantity || 1;
                const price = item.price ? ` - $${parseFloat(item.price).toFixed(2)}` : '';
                message += `${index + 1}. ${itemName} x ${quantity}${price}\n`;
            });
        }
        
        if (orderData.total_amount) {
            message += `\n💰 **សរុបទឹកប្រាក់:** $${parseFloat(orderData.total_amount).toFixed(2)}\n`;
        }
        
        if (orderData.order_number) {
            message += `📋 **លេខកូដ:** ${orderData.order_number}\n`;
        }
        
        message += `\n⏰ **ពេលវេលា:** ${new Date().toLocaleString('km-KH')}`;
        
        const url = `https://api.telegram.org/bot${TELEGRAM_BOT_TOKEN}/sendMessage`;
        const data = JSON.stringify({
            chat_id: YOUR_CHAT_ID,
            text: message,
            parse_mode: 'Markdown'
        });
        
        return new Promise((resolve, reject) => {
            const req = https.request(url, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'Content-Length': data.length
                }
            }, (res) => {
                let responseData = '';
                res.on('data', (chunk) => {
                    responseData += chunk;
                });
                res.on('end', () => {
                    try {
                        const result = JSON.parse(responseData);
                        if (result.ok) {
                            resolve(true);
                        } else {
                            console.error('Telegram API error:', result);
                            resolve(false);
                        }
                    } catch (e) {
                        console.error('Error parsing Telegram response:', e);
                        resolve(false);
                    }
                });
            });
            
            req.on('error', (error) => {
                console.error('Telegram request error:', error);
                resolve(false);
            });
            
            req.write(data);
            req.end();
        });
        
    } catch (error) {
        console.error('Telegram function error:', error);
        return false;
    }
}

// Test endpoint
app.get('/api/test', (req, res) => {
    res.json({ 
        message: '✅ Server is running!',
        timestamp: new Date().toISOString()
    });
});

// Simple auth endpoints for testing
app.post('/api/login', async (req, res) => {
    try {
        const { email, password } = req.body;
        
        console.log('Login attempt:', email);
        
        // Demo user for testing
        if (email === 'test@example.com' && password === '123456') {
            const token = jwt.sign(
                { id: 1, email: email },
                'demo_secret_key',
                { expiresIn: '7d' }
            );
            
            return res.json({
                message: 'ចូលគណនីជោគជ័យ',
                user: {
                    id: 1,
                    name: 'អ្នកប្រើប្រាស់សាកល្បង',
                    email: email,
                    phone: '012345678',
                    address: 'ភ្នំពេញ'
                },
                token: token
            });
        }
        
        // If database is connected, check real users
        if (connection) {
            const [users] = await connection.execute(
                'SELECT * FROM users WHERE email = ?',
                [email]
            );
            
            if (users.length > 0) {
                const user = users[0];
                const isValid = await bcrypt.compare(password, user.password);
                
                if (isValid) {
                    const token = jwt.sign(
                        { id: user.id, email: user.email },
                        process.env.JWT_SECRET || 'your_jwt_secret',
                        { expiresIn: '7d' }
                    );
                    
                    delete user.password;
                    
                    return res.json({
                        message: 'ចូលគណនីជោគជ័យ',
                        user,
                        token
                    });
                }
            }
        }
        
        return res.status(400).json({ message: 'អ៊ីមែលឬលេខសំងាត់មិនត្រឹមត្រូវ' });
        
    } catch (error) {
        console.error('Login error:', error);
        res.status(500).json({ message: 'មានបញ្ហាក្នុងការចូលគណនី' });
    }
});

app.post('/api/register', async (req, res) => {
    try {
        const { name, email, password } = req.body;
        
        console.log('Register attempt:', name, email);
        
        // For demo, just create a fake user
        const token = jwt.sign(
            { id: Date.now(), email: email },
            'demo_secret_key',
            { expiresIn: '7d' }
        );
        
        return res.status(201).json({
            message: 'ចុះឈ្មោះជោគជ័យ',
            user: {
                id: Date.now(),
                name: name,
                email: email,
                phone: '',
                address: ''
            },
            token: token
        });
        
        // Note: Real database code would go here
        
    } catch (error) {
        console.error('Register error:', error);
        res.status(500).json({ message: 'មានបញ្ហាក្នុងការចុះឈ្មោះ' });
    }
});

// Protected endpoint example
app.get('/api/user', (req, res) => {
    const authHeader = req.headers['authorization'];
    const token = authHeader && authHeader.split(' ')[1];
    
    if (!token) {
        return res.status(401).json({ message: 'អត់មាន token' });
    }
    
    try {
        const decoded = jwt.verify(token, 'demo_secret_key');
        res.json({
            id: decoded.id,
            name: 'អ្នកប្រើប្រាស់សាកល្បង',
            email: decoded.email,
            phone: '012345678',
            address: 'ភ្នំពេញ'
        });
    } catch (error) {
        res.status(403).json({ message: 'Token មិនត្រឹមត្រូវ' });
    }
});

// Orders endpoint with Telegram integration
app.post('/api/orders', async (req, res) => {
    const authHeader = req.headers['authorization'];
    const token = authHeader && authHeader.split(' ')[1];
    
    if (!token) {
        return res.status(401).json({ message: 'តម្រូវឲ្យចូលគណនី' });
    }
    
    try {
        const decoded = jwt.verify(token, 'demo_secret_key');
        
        // Generate order ID
        const orderId = 'KROMA-' + Date.now();
        
        const orderData = {
            id: orderId,
            order_number: orderId,
            total_amount: req.body.total,
            status: 'pending',
            items: req.body.items,
            shippingAddress: req.body.shippingAddress,
            paymentMethod: req.body.paymentMethod,
            customerInfo: req.body.customerInfo
        };
        
        // Send to Telegram
        const telegramSent = await sendToTelegram(orderData, {
            name: req.body.customerName || decoded.email,
            phone: req.body.phone || '',
            telegram: req.body.telegramUsername || ''
        });
        
        res.status(201).json({
            message: 'ការបញ្ជាទិញជោគជ័យ' + (telegramSent ? ' និងបានផ្ញើទៅ Telegram!' : ''),
            order: orderData,
            telegram_sent: telegramSent
        });
    } catch (error) {
        console.error('Order error:', error);
        res.status(403).json({ message: 'Token មិនត្រឹមត្រូវ' });
    }
});

// Serve static files if needed
app.use(express.static('.'));

const PORT = process.env.PORT || 5000;

async function startServer() {
    await connectDB();
    
    app.listen(PORT, () => {
        console.log(`🚀 Server running at http://localhost:${PORT}`);
        console.log(`📡 Test endpoint: http://localhost:${PORT}/api/test`);
        console.log(`🔐 Demo login: test@example.com / 123456`);
        console.log(`🤖 Telegram bot ready for orders`);
    });
}

startServer().catch(console.error);