#!/bin/bash

# Setup script for Laravel storage and default images
echo "Setting up Laravel storage and default images..."

# Create storage symlink
echo "Creating storage symlink..."
php artisan storage:link

# Create default avatar directories if they don't exist
mkdir -p public/avatars
mkdir -p storage/app/public/logos/businesses
mkdir -p storage/app/public/signatures/businesses  
mkdir -p storage/app/public/stamps/businesses

# Create simple default images (SVG placeholders)
echo "Creating default placeholder images..."

# Default user avatar
cat > public/avatars/empty-user.jpg << 'EOF'
<svg width="200" height="200" xmlns="http://www.w3.org/2000/svg">
  <rect width="200" height="200" fill="#f3f4f6"/>
  <circle cx="100" cy="80" r="30" fill="#d1d5db"/>
  <path d="M50 150 Q100 130 150 150 L150 200 L50 200 Z" fill="#d1d5db"/>
  <text x="100" y="190" text-anchor="middle" fill="#6b7280" font-size="12">LOGO</text>
</svg>
EOF

# Default signature
cat > public/avatars/empty-signature.jpg << 'EOF'
<svg width="200" height="200" xmlns="http://www.w3.org/2000/svg">
  <rect width="200" height="200" fill="#f8fafc"/>
  <path d="M40 100 Q80 80 120 100 T180 100" stroke="#94a3b8" stroke-width="2" fill="none"/>
  <path d="M60 120 Q100 100 140 120 T200 120" stroke="#94a3b8" stroke-width="2" fill="none"/>
  <text x="100" y="160" text-anchor="middle" fill="#64748b" font-size="12">SIGNATURE</text>
</svg>
EOF

# Default stamp
cat > public/avatars/empty-stamp.jpg << 'EOF'
<svg width="200" height="200" xmlns="http://www.w3.org/2000/svg">
  <rect width="200" height="200" fill="#fef7f0"/>
  <circle cx="100" cy="100" r="60" stroke="#fb923c" stroke-width="3" fill="none"/>
  <text x="100" y="95" text-anchor="middle" fill="#ea580c" font-size="14" font-weight="bold">COMPANY</text>
  <text x="100" y="115" text-anchor="middle" fill="#ea580c" font-size="12">STAMP</text>
</svg>
EOF

echo "Setup complete!"
echo "- Storage symlink created"
echo "- Default placeholder images created in public/avatars/"
echo "- Storage directories created"

# Set proper permissions
chmod -R 755 public/avatars
chmod -R 755 storage/app/public

echo "Permissions set correctly."