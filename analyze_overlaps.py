import psycopg2
import time
from tqdm import tqdm
import os
import sys

# --- KONFIGURASI DATABASE ---
# Pastikan konfigurasi ini sesuai dengan .env Anda
DB_CONFIG = {
    'dbname': 'db_webgis_kediri',      # Ganti dengan nama database Anda
    'user': 'user_webgis',             # Ganti dengan username database
    'password': 'admin321',            # Ganti dengan password database
    'host': '127.0.0.1',
    'port': '5432'
}

BATCH_SIZE = 500  # Jumlah data per proses

def get_db_connection():
    try:
        conn = psycopg2.connect(**DB_CONFIG)
        conn.autocommit = True
        return conn
    except psycopg2.OperationalError as e:
        print(f"[ERROR] Gagal koneksi database: {e}")
        exit(1)

def run_analysis():
    # Menggunakan [START] menggantikan emotikon roket
    print("[START] MEMULAI ANALISIS OVERLAP (LAYER UTAMA ONLY)")
    
    conn = get_db_connection()
    cur = conn.cursor()

    try:
        # 1. PERSIAPAN
        print("[INFO] Menyiapkan Database...")
        cur.execute("TRUNCATE TABLE overlap_results")
        cur.execute("VACUUM ANALYZE spatial_features")
        
        # 2. AMBIL TOTAL DATA (FILTER: HANYA LAYER UTAMA / AUTO_HAK)
        print("[INFO] Menghitung data pada Layer Utama...")
        cur.execute("""
            SELECT MIN(f.id), MAX(f.id), COUNT(f.id) 
            FROM spatial_features f
            JOIN layers l ON f.layer_id = l.id
            WHERE l.mode = 'auto_hak'
        """)
        result = cur.fetchone()
        
        if result is None or result[2] == 0:
            print("[WARN] Tidak ada data pada Layer Utama (auto_hak)!")
            return

        min_id, max_id, total_rows = result
        print(f"[OK] Ditemukan {total_rows:,} aset pada Layer Utama.")
        print(f"[INFO] Batch Size: {BATCH_SIZE}")

        # 3. LOOPING BATCH
        current_id = min_id - 1
        # TQDM biasanya aman, tapi jika error bisa dihapus atau diatur ascii=True
        pbar = tqdm(total=total_rows, unit="aset", desc="Processing", ascii=True)

        while current_id < max_id:
            # Ambil Batch ID (HANYA LAYER UTAMA)
            cur.execute(f"""
                SELECT f.id FROM spatial_features f
                JOIN layers l ON f.layer_id = l.id
                WHERE f.id > {current_id} 
                AND l.mode = 'auto_hak'
                ORDER BY f.id ASC 
                LIMIT {BATCH_SIZE}
            """)
            
            batch_ids = [row[0] for row in cur.fetchall()]
            
            if not batch_ids:
                if current_id < max_id:
                     current_id = max_id
                break

            last_batch_id = batch_ids[-1]
            ids_string = ",".join(map(str, batch_ids))

            # 4. QUERY SPASIAL
            query = f"""
                INSERT INTO overlap_results (id_1, id_2, aset_1, aset_2, desa, kecamatan, luas_overlap, created_at, updated_at)
                SELECT 
                    a.id, b.id, a.name, b.name,
                    COALESCE(a.properties->'raw_data'->>'KELURAHAN', '-'),
                    COALESCE(a.properties->'raw_data'->>'KECAMATAN', '-'),
                    ST_Area(ST_Intersection(a.geom, b.geom)::geography),
                    NOW(), NOW()
                FROM spatial_features a
                JOIN spatial_features b ON 
                    a.id < b.id 
                    AND a.geom && b.geom 
                    AND ST_Intersects(a.geom, b.geom)
                JOIN layers lb ON b.layer_id = lb.id 
                WHERE 
                    a.id IN ({ids_string}) 
                    AND lb.mode = 'auto_hak'
                    AND ST_IsValid(a.geom::geometry) 
                    AND ST_IsValid(b.geom::geometry)
                    AND ST_Area(ST_Intersection(a.geom, b.geom)::geography) > 1
            """
            
            cur.execute(query)
            
            # Update Progress
            pbar.update(len(batch_ids))
            current_id = last_batch_id

        pbar.close()
        print("\n[SELESAI] Analisis tumpang tindih (Layer Utama) berhasil.")

    except Exception as e:
        print(f"\n[ERROR] {e}")
    
    finally:
        if cur: cur.close()
        if conn: conn.close()

if __name__ == "__main__":
    run_analysis()