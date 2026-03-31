#!/bin/bash
# Path to EDirect (Make sure it's installed)
export PATH=${PATH}:${HOME}/edirect

# Array of categories
declare -A queries
queries["aves_g6p"]="glucose-6-phosphatase [PROT] AND Aves [ORGN]"
queries["mammal_abc"]="ABC transporter [PROT] AND Mammalia [ORGN]"
queries["rodent_kinase"]="kinase [PROT] AND Rodentia [ORGN]"
queries["vertebrate_ac"]="adenyl cyclase [PROT] AND Vertebrata [ORGN]"

# Create data folder
mkdir -p ../data

for key in "${!queries[@]}"; do
    echo "Fetching $key..."
    # Get Top 5 sequences in FASTA format
    esearch -db protein -query "${queries[$key]}" | efetch -format fasta > "../data/${key}.fasta"
done
echo "Fetch complete."
