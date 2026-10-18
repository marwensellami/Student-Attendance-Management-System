import sys
import os
import json
import mysql.connector
from deepface import DeepFace
import shutil

# Configuration de la base de données
DB_CONFIG = {
    'host': 'localhost',
    'user': 'root',
    'password': '',
    'database': 'presence_cours',
    'port': 4306
}

def get_student_photos_from_db():
    """Récupère les photos des étudiants depuis la base de données"""
    try:
        print("🔍 Connexion à la base de données...", file=sys.stderr)
        conn = mysql.connector.connect(**DB_CONFIG)
        cursor = conn.cursor(dictionary=True)
        
        cursor.execute("""
            SELECT u.id, u.nom, u.prenom, u.photo_path 
            FROM users u 
            WHERE u.role = 'etudiant' AND u.photo_path IS NOT NULL AND u.is_active = 1
        """)
        
        students = cursor.fetchall()
        cursor.close()
        conn.close()
        
        print(f"📊 {len(students)} étudiants trouvés dans la base", file=sys.stderr)
        
        return students
        
    except Exception as e:
        print(f"❌ Erreur DB: {str(e)}", file=sys.stderr)
        return []

def create_temp_db_folder(students):
    """Crée un dossier temporaire avec toutes les photos des étudiants"""
    temp_dir = "C:\\xampp\\htdocs\\presence_cours\\assets\\temp_db"
    
    # Créer le dossier temporaire
    if os.path.exists(temp_dir):
        shutil.rmtree(temp_dir)
    os.makedirs(temp_dir)
    
    student_info = {}
    
    for student in students:
        if student['photo_path']:
            # Chemin source
            source_path = "C:\\xampp\\htdocs\\presence_cours\\" + student['photo_path']
            source_path = os.path.normpath(source_path)
            
            if os.path.exists(source_path):
                # Chemin destination dans le temp folder
                filename = f"student_{student['id']}_{os.path.basename(source_path)}"
                dest_path = os.path.join(temp_dir, filename)
                
                # Copier le fichier
                shutil.copy2(source_path, dest_path)
                
                student_info[filename] = {
                    'id': student['id'],
                    'nom': student['nom'],
                    'prenom': student['prenom'],
                    'photo_path': student['photo_path']
                }
                
                print(f"✅ Photo copiée: {filename}", file=sys.stderr)
            else:
                print(f"❌ Photo source introuvable: {source_path}", file=sys.stderr)
    
    return temp_dir, student_info

def verify_face(probe_path, threshold=0.6):
    """Vérifie si le visage correspond à un étudiant enregistré"""
    try:
        print(f"🔍 Début vérification faciale pour: {probe_path}", file=sys.stderr)
        
        # Récupérer les étudiants depuis la base
        students = get_student_photos_from_db()
        
        if not students:
            return {"status": "error", "msg": "Aucun étudiant avec photo trouvé dans la base"}
        
        # Créer le dossier temporaire avec toutes les photos
        temp_db_dir, student_info = create_temp_db_folder(students)
        
        if not student_info:
            return {"status": "error", "msg": "Aucune photo trouvée sur le disque"}
        
        print(f"🎯 {len(student_info)} photos dans la base temporaire", file=sys.stderr)
        
        # Utiliser DeepFace pour trouver la correspondance
        try:
            print("🤖 Lancement de DeepFace...", file=sys.stderr)
            
            result = DeepFace.find(
                img_path=probe_path,
                db_path=temp_db_dir,
                model_name='VGG-Face',
                detector_backend='opencv',
                distance_metric='cosine',
                enforce_detection=False,
                align=True,
                silent=True
            )
            
            print("✅ DeepFace terminé", file=sys.stderr)
            
            if len(result) > 0 and not result[0].empty:
                # Prendre la meilleure correspondance
                best_match = result[0].iloc[0]
                distance = float(best_match['distance'])  # Convertir en float standard
                matched_file = os.path.basename(best_match['identity'])
                
                print(f"📊 Meilleure correspondance: {matched_file} (distance: {distance})", file=sys.stderr)
                
                # Vérifier le seuil de confiance
                if distance <= threshold:
                    # Récupérer les infos de l'étudiant
                    student_data = student_info.get(matched_file)
                    
                    if student_data:
                        confidence = 1 - distance
                        return {
                            "status": "found",
                            "student_id": student_data['id'],
                            "student_name": f"{student_data['prenom']} {student_data['nom']}",
                            "confidence": round(confidence, 4),
                            "distance": round(distance, 4),
                            "matched_photo": matched_file
                        }
                    else:
                        return {"status": "not_found", "msg": "Étudiant non identifié"}
                else:
                    return {
                        "status": "not_found", 
                        "msg": f"Distance trop élevée: {distance:.4f} > {threshold}"
                    }
            else:
                return {"status": "not_found", "msg": "Aucune correspondance trouvée"}
                
        except Exception as e:
            print(f"❌ Erreur DeepFace: {str(e)}", file=sys.stderr)
            return {"status": "error", "msg": f"Erreur reconnaissance: {str(e)}"}
            
    except Exception as e:
        print(f"❌ Erreur générale: {str(e)}", file=sys.stderr)
        return {"status": "error", "msg": f"Erreur générale: {str(e)}"}

def main():
    """Fonction principale"""
    if len(sys.argv) < 2:
        result = {"status": "error", "msg": "Aucune image transmise"}
        print(json.dumps(result))
        sys.exit(1)
    
    probe_path = sys.argv[1]
    
    # Nettoyer le chemin
    probe_path = probe_path.strip('"')
    probe_path = os.path.normpath(probe_path)
    
    print(f"🔄 Traitement de: {probe_path}", file=sys.stderr)
    
    if not os.path.exists(probe_path):
        result = {"status": "error", "msg": f"Fichier introuvable: {probe_path}"}
        print(json.dumps(result))
        sys.exit(1)
    
    try:
        # Vérifier la présence faciale
        result = verify_face(probe_path, threshold=0.6)
        
        print(f"📋 Résultat final: {result}", file=sys.stderr)
        print(json.dumps(result))
        
    except Exception as e:
        error_result = {"status": "error", "msg": f"Erreur inattendue: {str(e)}"}
        print(f"💥 Erreur inattendue: {str(e)}", file=sys.stderr)
        print(json.dumps(error_result))

if __name__ == "__main__":
    main()