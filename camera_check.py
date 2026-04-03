#!/usr/bin/env python3
"""
Script de vérification des caméras
-----------------------------------
Toutes les 3 minutes, ce script :
1. Récupère toutes les caméras de la base de données
2. Fait un ping sur chaque adresse IP
3. Met à jour le champ 'etat' (TRUE/FALSE) selon le résultat
"""

import subprocess
import time
import mysql.connector
from mysql.connector import Error

# ============================================================
# CONFIGURATION
# ============================================================
DB_CONFIG = {
    'host':     'localhost',
    'database': 'LAMP',
    'user':     'jules',
    'password': 'root'
}

INTERVALLE_SECONDES = 180  # 3 minutes


def ping(ip):
    """Envoie 1 ping, retourne True si l'équipement répond réellement."""
    try:
        result = subprocess.run(
            ['ping', '-c', '1', '-W', '1', ip],
            stdout=subprocess.PIPE,
            stderr=subprocess.DEVNULL
        )
        # returncode == 0 peut aussi indiquer un ICMP "Destination Host Unreachable"
        # envoyé par un routeur. On vérifie que la cible a vraiment répondu.
        output = result.stdout.decode(errors='ignore')
        return result.returncode == 0 and '1 received' in output
    except Exception:
        return False


def mettre_a_jour_etat(cursor, camera_id, en_ligne):
    nouvel_etat = 'TRUE' if en_ligne else 'FALSE'
    cursor.execute(
        "UPDATE network_table SET etat = %s WHERE id = %s",
        (nouvel_etat, camera_id)
    )


def verifier_cameras():
    conn = None
    try:
        conn = mysql.connector.connect(**DB_CONFIG)
        cursor = conn.cursor(dictionary=True)

        cursor.execute(
            "SELECT id, nom_equipement, adresse_ip, type_peripherique FROM network_table"
        )
        cameras = cursor.fetchall()

        print(f"\n[{time.strftime('%H:%M:%S')}] Vérification de {len(cameras)} équipement(s)...")

        for cam in cameras:
            ip     = cam['adresse_ip']
            nom    = cam['nom_equipement']
            cam_id = cam['id']

            if not ip:
                print(f"  [--] {nom} : pas d'IP, ignorée")
                continue

            en_ligne = ping(ip)
            statut   = "EN LIGNE  " if en_ligne else "HORS LIGNE"
            print(f"  [{statut}] {nom} ({ip}) [{cam['type_peripherique']}]")
            mettre_a_jour_etat(cursor, cam_id, en_ligne)

        conn.commit()
        print("  --> Base de données mise à jour.")

    except Error as e:
        print(f"Erreur base de données : {e}")

    finally:
        if conn and conn.is_connected():
            cursor.close()
            conn.close()


if __name__ == "__main__":
    print("Démarrage du script de surveillance des caméras...")
    print(f"Intervalle : toutes les {INTERVALLE_SECONDES // 60} minutes")
    print("Appuyez sur Ctrl+C pour arrêter.\n")

    while True:
        verifier_cameras()
        print(f"Prochain check dans {INTERVALLE_SECONDES // 60} minutes...\n")
        time.sleep(INTERVALLE_SECONDES)
