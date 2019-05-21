import networkx as nx
base = "~/solr/crawl_data/"
target = "./external_pageRankFile.txt"
edgelistPath="./Edgelist.txt"
G = nx.read_edgelist(edgelistPath, create_using=nx.DiGraph())
pr = nx.pagerank(G, alpha=0.85, personalization=None, max_iter=30, tol=1e-06, nstart=None, weight='weight',dangling=None)
file = open(target, "w")
for k, v in pr.items():
    print(k, v)
    file.write(base + "%s=%f\n"%(k, v));
file.flush();
file.close();
